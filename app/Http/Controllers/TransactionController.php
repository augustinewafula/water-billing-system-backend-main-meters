<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditAccountRequest;
use App\Http\Requests\DebitAccountRequest;
use App\Http\Requests\TransferTransactionRequest;
use App\Models\ConnectionFeePayment;
use App\Models\CreditAccount;
use App\Models\MeterBilling;
use App\Models\MeterToken;
use App\Models\MonthlyServiceChargePayment;
use App\Models\MpesaTransaction;
use App\Models\UnaccountedDebt;
use App\Models\User;
use App\Services\MpesaService;
use App\Services\TransactionService;
use App\Traits\ConvertsPhoneNumberToInternationalFormat;
use App\Traits\CreditsUserAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Str;
use DB;
use Throwable;

class TransactionController extends Controller
{
    use CreditsUserAccount,
        ConvertsPhoneNumberToInternationalFormat;

    public function __construct()
    {
        $this->middleware('permission:mpesa-transaction-list', ['only' => ['index', 'show']]);
    }

    public function queryTransactionStatusResultCallback(Request $request, MpesaService $mpesaService): JsonResponse
    {
        \Log::info('Query transaction status result callback: '. $request);
        $mpesaService->handleTransactionStatusResult($request);
        return response()->json('ok');
    }

    public function queryTransactionStatusQueueTimeoutCallback(Request $request, MpesaService $mpesaService): JsonResponse
    {
        \Log::info('Query transaction status queue timeout callback: '. $request);
        $mpesaService->handleTransactionStatusQueueTimeout($request);
        return response()->json('ok');
    }

    public function pullTransactionConfirmation(Request $request): Response
    {
        Log::info(['pull transaction confirmation request' => $request->all()]);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml; charset=utf-8');
        $response->setContent(json_encode(['PullTransactionConfirmationResult' => 'Success'], JSON_THROW_ON_ERROR));

        return $response;

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

        $hashedPhoneSelect = DB::raw("
            CASE
                WHEN CHAR_LENGTH(mpesa_transactions.MSISDN) = 64 THEN
                    IF(SHA2(CONCAT('254', SUBSTR(users.phone, 2)), 256) = mpesa_transactions.MSISDN, users.phone, 'UNKNOWN')
                ELSE
                    IF(CONCAT('254', SUBSTR(users.phone, 2)) = mpesa_transactions.MSISDN, users.phone, 'UNKNOWN')
            END as phone_number
        ");


        $postpaid_transactions = MpesaTransaction::with('creditedBy:id,name')->select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransTime as transaction_number', 'mpesa_transactions.TransAmount as amount', $hashedPhoneSelect, 'mpesa_transactions.created_at as transaction_time', 'mpesa_transactions.credited', 'mpesa_transactions.credited_by', 'mpesa_transactions.reason_for_crediting', 'users.name', 'users.account_number')
            ->join('meter_billings', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id')
            ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('users', 'users.meter_id', 'meters.id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $postpaid_transactions = $this->filterQuery($postpaid_transactions, $request);

        $prepaid_transactions = MpesaTransaction::with('creditedBy')->select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransTime as transaction_number', 'mpesa_transactions.TransAmount as amount', $hashedPhoneSelect, 'mpesa_transactions.created_at as transaction_time', 'mpesa_transactions.credited', 'mpesa_transactions.credited_by', 'mpesa_transactions.reason_for_crediting', 'users.name', 'users.account_number')
            ->join('meter_tokens', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('users', 'users.meter_id', 'meters.id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $prepaid_transactions = $this->filterQuery($prepaid_transactions, $request);

        $unaccounted_debt_transactions = MpesaTransaction::with('creditedBy')->select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransTime as transaction_number', 'mpesa_transactions.TransAmount as amount', $hashedPhoneSelect, 'mpesa_transactions.created_at as transaction_time', 'mpesa_transactions.credited', 'mpesa_transactions.credited_by', 'mpesa_transactions.reason_for_crediting', 'users.name', 'users.account_number')
            ->join('unaccounted_debts', 'mpesa_transactions.id', 'unaccounted_debts.mpesa_transaction_id')
            ->join('users', 'users.id', 'unaccounted_debts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $unaccounted_debt_transactions = $this->filterQuery($unaccounted_debt_transactions, $request);

        $connection_fee_transactions = MpesaTransaction::with('creditedBy')->select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransTime as transaction_number', 'mpesa_transactions.TransAmount as amount', $hashedPhoneSelect, 'mpesa_transactions.created_at as transaction_time', 'mpesa_transactions.credited', 'mpesa_transactions.credited_by', 'mpesa_transactions.reason_for_crediting', 'users.name', 'users.account_number')
            ->join('connection_fee_payments', 'mpesa_transactions.id', 'connection_fee_payments.mpesa_transaction_id')
            ->join('connection_fees', 'connection_fees.id', 'connection_fee_payments.connection_fee_id')
            ->join('users', 'users.id', 'connection_fees.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $connection_fee_transactions = $this->filterQuery($connection_fee_transactions, $request);

        $credit_account_transactions = MpesaTransaction::with('creditedBy')->select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransTime as transaction_number', 'mpesa_transactions.TransAmount as amount', $hashedPhoneSelect, 'mpesa_transactions.created_at as transaction_time', 'mpesa_transactions.credited', 'mpesa_transactions.credited_by', 'mpesa_transactions.reason_for_crediting', 'users.name', 'users.account_number')
            ->join('credit_accounts', 'mpesa_transactions.id', 'credit_accounts.mpesa_transaction_id')
            ->join('users', 'users.id', 'credit_accounts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $credit_account_transactions = $this->filterQuery($credit_account_transactions, $request);

        $monthly_service_charge_transactions = MpesaTransaction::with('creditedBy')->select(
            'mpesa_transactions.id',
            'mpesa_transactions.TransID as transaction_reference',
            'mpesa_transactions.TransTime as transaction_number',
            'mpesa_transactions.TransAmount as amount',
            $hashedPhoneSelect,
            'mpesa_transactions.created_at as transaction_time',
            'mpesa_transactions.credited',
            'mpesa_transactions.credited_by',
            'mpesa_transactions.reason_for_crediting',
            'users.name',
            'users.account_number'
        )
            ->join('monthly_service_charge_payments', 'mpesa_transactions.id', 'monthly_service_charge_payments.mpesa_transaction_id')
            ->join('monthly_service_charges', 'monthly_service_charges.id', 'monthly_service_charge_payments.monthly_service_charge_id')
            ->join('users', 'users.id', 'monthly_service_charges.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');

        $monthly_service_charge_transactions = $this->filterQuery($monthly_service_charge_transactions, $request);


        $prepaid_transactions->union($postpaid_transactions);
        $prepaid_transactions->union($unaccounted_debt_transactions);
        $prepaid_transactions->union($connection_fee_transactions);
        $prepaid_transactions->union($credit_account_transactions);
        $prepaid_transactions->union($monthly_service_charge_transactions);
        $sum = $prepaid_transactions->sum('amount');

        if ($sortBy !== 'undefined') {
            $prepaid_transactions->orderBy($sortBy, $sortOrder);
        }
        if ($request->has('forExport')){
            $data = $prepaid_transactions->get()
                ->makeHidden('id');

            return response()->json($data);
        }

        $perPage = 10;
        if ($request->has('perPage')){
            $perPage = $request->perPage;
        }
        $prepaid_transactions = $prepaid_transactions->paginate($perPage);
        $data = ['transactions' => $prepaid_transactions, 'sum' => $sum];

        return response()->json($data);


    }

    public function creditAccount(CreditAccountRequest $request, MpesaService $mpesaService): JsonResponse
    {
        try {
            $this->creditUserAccount($request, $mpesaService);
        }catch (Throwable $throwable) {
            \Log::error($throwable);
            $response = ['message' => 'Failed to credit account: '.$throwable->getMessage()];
            return response()->json($response, 422);
        }
        return response()->json('success', 201);
    }

    public function debitAccount(DebitAccountRequest $request): JsonResponse
    {
        try {
            $user = User::findOrFail($request->user_id);

            // Determine the amount to be debited, considering the user's credit
            $debitAmount = $request->amount;

            // If there is any credit, use it towards the debit amount
            if ($user->account_balance > 0) {
                // Calculate how much credit can be used
                $creditToUse = min($user->account_balance, $debitAmount);

                // Reduce the debit amount by the credit used
                $debitAmount -= $creditToUse;

                // Update the credit field with the remaining amount, ensuring it's not less than 0
                $user->account_balance = max($user->account_balancea - $creditToUse, 0);
            }

            // Update the unaccounted_debt by the calculated debit amount
            $user->unaccounted_debt += $debitAmount;

            // Save the changes to the user
            $user->save();

        } catch (Throwable $throwable) {
            \Log::error($throwable);
            $response = ['message' => 'Failed to debit account: '.$throwable->getMessage()];
            return response()->json($response, 422);
        }

        return response()->json('success', 201);
    }


    public function transfer(TransferTransactionRequest $request, TransactionService $transactionService): JsonResponse
    {
        try {
            $transactionService->transfer($request->from_account_number, $request->to_account_number, $request->transaction_id);
            return response()->json('success', 201);
        } catch (Throwable $throwable) {
            \Log::error($throwable);
            $response = ['message' => 'Failed to transfer: '.$throwable->getMessage()];
            return response()->json($response, 422);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
{
    $userDetails = $this->getUser($id);
    $transaction = MpesaTransaction::where('id', $id)->first();

    if ($userDetails) {
        $transaction->MSISDN = $userDetails['phone_number']; // Replace MSISDN
        $user = $userDetails['user'];
    } else {
        $user = null;
    }

    return response()->json([
        'user' => $user,
        'transaction' => $transaction
    ]);
}


    public function getUser($transaction_id)
{
    $hashedPhoneComparison = DB::raw("
        CASE
            WHEN CHAR_LENGTH(mpesa_transactions.MSISDN) = 64 THEN
                SHA2(CONCAT('254', SUBSTR(users.phone, 2)), 256) = mpesa_transactions.MSISDN
            ELSE
                CONCAT('254', SUBSTR(users.phone, 2)) = mpesa_transactions.MSISDN
        END
    ");

    $baseQuery = function ($joinTable, $joinCondition) use ($transaction_id, $hashedPhoneComparison) {
        return User::selectRaw("users.*, IF($hashedPhoneComparison, users.phone, 'UNKNOWN') as modified_phone_number")
            ->join($joinTable, $joinCondition, '=', 'users.meter_id')
            ->join('mpesa_transactions', 'mpesa_transactions.id', '=', 'mpesa_transaction_id')
            ->where('mpesa_transactions.id', $transaction_id);
    };

    $relations = [
        ['meter_tokens', 'meter_tokens.meter_id'],
        ['meter_readings', 'meter_readings.id'],
        ['unaccounted_debts', 'unaccounted_debts.user_id'],
        ['monthly_service_charge_payments', 'monthly_service_charge_payments.monthly_service_charge_id'],
        ['credit_accounts', 'credit_accounts.user_id'],
        ['connection_fee_payments', 'connection_fee_payments.connection_fee_id']
    ];

    foreach ($relations as [$joinTable, $joinCondition]) {
        $user = $baseQuery($joinTable, $joinCondition)->first();
        if ($user) {
            return [
                'user' => $user,
                'phone_number' => $user->modified_phone_number
            ];
        }
    }

    return null;
}




    private function filterQuery(Builder $query, Request $request): Builder
    {
        $search = $request->query('search');
        $search_filter = $request->query('search_filter');
        $stationId = $request->query('station_id');
        $fromDate = $request->query('fromDate');
        $toDate = $request->query('toDate');
        $credit = $request->query('credit');
        $noCredit = $request->query('noCredit');
        if ($request->has('search') && Str::length($request->query('search')) > 0) {
            $query = $query->where(function ($query) use ($search, $search_filter) {
                $query->where($search_filter, 'like', '%' . $search . '%');
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

        if ($credit === 'true' && $noCredit === 'true') {
            // If both credit and noCredit are true, return all records
            // where credited is either true or false (credit = 1 or credit = 0)
            $query->whereIn('mpesa_transactions.credited', [true, false]);
        } elseif ($credit === 'true') {
            // If only credit is true, return records with credited = true (credit = 1)
            $query->where('mpesa_transactions.credited', true);
        } elseif ($noCredit === 'true') {
            // If only noCredit is true, return records with credited = false (credit = 0)
            $query->where('mpesa_transactions.credited', false);
        }

        return $query;
    }

    public static function isJoined($query, $table): bool
    {
        return collect($query->getQuery()->joins)->pluck('table')->contains($table);
    }
}
