<?php

namespace App\Http\Controllers;

use App\Models\MeterBilling;
use App\Models\MeterToken;
use App\Models\MonthlyServiceCharge;
use App\Models\MonthlyServiceChargePayment;
use App\Models\MpesaTransaction;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Str;
use Throwable;

class TransactionController extends Controller
{
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

        $postpaid_transactions = MpesaTransaction::select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time', 'users.account_number')
            ->join('meter_billings', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id')
            ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('users', 'users.meter_id', 'meters.id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $postpaid_transactions = $this->filterQuery($postpaid_transactions, $request);

        $prepaid_transactions = MpesaTransaction::select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time', 'users.account_number')
            ->join('meter_tokens', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('users', 'users.meter_id', 'meters.id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $prepaid_transactions = $this->filterQuery($prepaid_transactions, $request);

        $prepaid_transactions->union($postpaid_transactions);

        return response()->json($prepaid_transactions->paginate(10));

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
        } catch (Throwable $throwable) {
            try {
                $user = MonthlyServiceChargePayment::select('users.*')
                    ->join('monthly_service_charges', 'monthly_service_charges.id', 'monthly_service_charge_payments.monthly_service_charge_id')
                    ->join('users', 'monthly_service_charges.user_id', 'users.id')
                    ->where('mpesa_transaction_id', $transaction_id)
                    ->first();
                throw_if($user === null);
            } catch (Throwable $throwable){
                $user = MeterToken::select('users.*')
                    ->join('users', 'meter_tokens.meter_id', 'users.meter_id')
                    ->where('mpesa_transaction_id', $transaction_id)
                    ->first();
            }
        }
        return $user;
    }

    private function filterQuery(Builder $query, Request $request): Builder
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');
        $fromDate = $request->query('fromDate');
        $toDate = $request->query('toDate');
        if ($request->has('search') && Str::length($request->query('search')) > 0) {
            $query = $query->where(function ($query) use ($search) {
                $query->where('mpesa_transactions.TransAmount', 'like', '%' . $search . '%');
                $query->orWhere('mpesa_transactions.MSISDN', 'like', '%' . $search . '%');
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

        if ($request->has('sortBy')) {
            $query = $query->orderBy($sortBy, $sortOrder);
        }

        return $query;
    }

    public static function isJoined($query, $table): bool
    {
        return collect($query->getQuery()->joins)->pluck('table')->contains($table);
    }
}
