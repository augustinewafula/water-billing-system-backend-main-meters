<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditAccountRequest;
use App\Http\Requests\MpesaTransactionRequest;
use App\Jobs\ProcessTransaction;
use App\Models\CreditAccount;
use App\Models\MeterBilling;
use App\Models\MeterToken;
use App\Models\MonthlyServiceCharge;
use App\Models\MonthlyServiceChargePayment;
use App\Models\MpesaTransaction;
use App\Models\UnaccountedDebt;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use App\Traits\StoresMpesaTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Str;
use Throwable;

class TransactionController extends Controller
{
    use StoresMpesaTransaction;

    public function __construct()
    {
        $this->middleware('permission:mpesa-transaction-list|unresolved-mpesa-transaction-list', ['only' => ['index', 'unresolvedTransactionIndex', 'show']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');

        $postpaid_transactions = MpesaTransaction::select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time', 'users.name', 'users.account_number')
            ->join('meter_billings', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id')
            ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('users', 'users.meter_id', 'meters.id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $postpaid_transactions = $this->filterQuery($postpaid_transactions, $request);

        $prepaid_transactions = MpesaTransaction::select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time', 'users.name', 'users.account_number')
            ->join('meter_tokens', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('users', 'users.meter_id', 'meters.id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $prepaid_transactions = $this->filterQuery($prepaid_transactions, $request);

        $unaccounted_debt_transactions = MpesaTransaction::select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time', 'users.name', 'users.account_number')
            ->join('unaccounted_debts', 'mpesa_transactions.id', 'unaccounted_debts.mpesa_transaction_id')
            ->join('users', 'users.id', 'unaccounted_debts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $unaccounted_debt_transactions = $this->filterQuery($unaccounted_debt_transactions, $request);

        $connection_fee_transactions = MpesaTransaction::select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time', 'users.name', 'users.account_number')
            ->join('connection_fee_payments', 'mpesa_transactions.id', 'connection_fee_payments.mpesa_transaction_id')
            ->join('connection_fees', 'connection_fees.id', 'connection_fee_payments.connection_fee_id')
            ->join('users', 'users.id', 'connection_fees.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $connection_fee_transactions = $this->filterQuery($connection_fee_transactions, $request);

        $credit_account_transactions = MpesaTransaction::select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time', 'users.name', 'users.account_number')
            ->join('credit_accounts', 'mpesa_transactions.id', 'credit_accounts.mpesa_transaction_id')
            ->join('users', 'users.id', 'credit_accounts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $credit_account_transactions = $this->filterQuery($credit_account_transactions, $request);

        $prepaid_transactions->union($postpaid_transactions);
        $prepaid_transactions->union($unaccounted_debt_transactions);
        $prepaid_transactions->union($connection_fee_transactions);
        $prepaid_transactions->union($credit_account_transactions);
        $sum = $prepaid_transactions->sum('amount');

        if ($sortBy !== 'undefined') {
            $prepaid_transactions->orderBy($sortBy, $sortOrder);
        }
        if ($request->has('forExport')){
            $data = $prepaid_transactions->get()
                ->makeHidden('id');
        }else{
            $prepaid_transactions = $prepaid_transactions->paginate(10);
            $data = ['transactions' => $prepaid_transactions, 'sum' => $sum];
        }

        return response()->json($data);

    }

    public function creditAccount(CreditAccountRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->user_id);
        if (empty($request->mpesa_transaction_reference)) {
            $transaction_id = 'SimulatedTransaction_'.now()->timestamp;
        } else {
            $transaction_id = $request->mpesa_transaction_reference;
        }

        $mpesa_request = new MpesaTransactionRequest();
        $mpesa_request->setMethod('POST');
        $mpesa_request->request->add([
            'TransID' => $transaction_id,
            'TransTime' => now()->timestamp,
            'TransAmount' => $request->amount,
            'FirstName' => $user->name,
            'BillRefNumber' => $user->account_number,
        ]);
        try {
            $mpesa_request->validate((new MpesaTransactionRequest)->rules());
            $mpesa_transaction = $this->storeMpesaTransaction($mpesa_request);
            ProcessTransaction::dispatch($mpesa_transaction);
        }catch (Throwable $throwable){
            \Log::error($throwable);
            $response = ['message' => 'Failed to credit account'];
            return response()->json($response, 422);
        }
        return response()->json('success', 201);
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {

        $user = $this->getUser($id);
        $transaction = MpesaTransaction::where('id', $id)->first();
        return response()->json([
            'user' => $user,
            'transaction' => $transaction
        ]);
    }

    public function getUser($transaction_id)
    {
        try {
            $user = MeterBilling::select('users.*')
                ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
                ->join('users', 'meter_readings.meter_id', 'users.meter_id')
                ->where('mpesa_transaction_id', $transaction_id)
                ->first();
            throw_if($user === null);
        } catch (Throwable) {
            try {
                $user = MeterToken::select('users.*')
                    ->join('users', 'meter_tokens.meter_id', 'users.meter_id')
                    ->where('mpesa_transaction_id', $transaction_id)
                    ->first();
                throw_if($user === null);
            } catch (Throwable){
                try {
                    $user = UnaccountedDebt::select('users.*')
                        ->join('users', 'unaccounted_debts.user_id', 'users.id')
                        ->where('mpesa_transaction_id', $transaction_id)
                        ->first();
                    throw_if($user === null);
                } catch (Throwable){
                    try {
                        $user = MonthlyServiceChargePayment::select('users.*')
                            ->join('monthly_service_charges', 'monthly_service_charges.id', 'monthly_service_charge_payments.monthly_service_charge_id')
                            ->join('users', 'monthly_service_charges.user_id', 'users.id')
                            ->where('mpesa_transaction_id', $transaction_id)
                            ->first();
                        throw_if($user === null);
                    } catch (Throwable){
                        $user = CreditAccount::select('users.*')
                            ->join('users', 'credit_accounts.user_id', 'users.id')
                            ->where('mpesa_transaction_id', $transaction_id)
                            ->first();
                    }
                }
            }
        }
        return $user;
    }

    private function filterQuery(Builder $query, Request $request): Builder
    {
        $search = $request->query('search');
        $stationId = $request->query('station_id');
        $fromDate = $request->query('fromDate');
        $toDate = $request->query('toDate');
        if ($request->has('search') && Str::length($request->query('search')) > 0) {
            $query = $query->where(function ($query) use ($search) {
                $query->where('mpesa_transactions.TransAmount', 'like', '%' . $search . '%')
                ->orWhere('mpesa_transactions.MSISDN', 'like', '%' . $search . '%')
                ->orWhere('users.account_number', 'like', '%' . $search . '%')
                ->orWhere('users.name', 'like', '%' . $search . '%');
            });
        }

        if (($request->has('fromDate') && Str::length($request->query('fromDate')) > 0) && ($request->has('toDate') && Str::length($request->query('toDate')) > 0)) {
            $formattedFromDate = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
            $formattedToDate = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
            $query = $query->whereBetween('mpesa_transactions.created_at', [$formattedFromDate, $formattedToDate]);
        }

        if ($request->has('station_id')) {
            $query = $query->where('meter_stations.id', $stationId);
        }

        if ($request->has('user_id')) {
            if (!self::isJoined($query, 'users')){
                $query = $query->join('users', 'users.meter_id', 'meters.id');
            }
            $query = $query->where('users.id', $request->query('user_id'));
        }

        return $query;
    }

    public static function isJoined($query, $table): bool
    {
        return collect($query->getQuery()->joins)->pluck('table')->contains($table);
    }
}
