<?php

namespace App\Http\Controllers;

use App\Models\MeterBilling;
use App\Models\MeterToken;
use App\Models\MpesaTransaction;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
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

        $postpaid_transactions = MpesaTransaction::select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time')
            ->join('meter_billings', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id')
            ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        $postpaid_transactions = $this->filterQuery($postpaid_transactions, $request);

        $prepaid_transactions = MpesaTransaction::select('mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time')
            ->join('meter_tokens', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
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
        try {
            $meter_id = MeterBilling::select('meter_readings.meter_id')
                ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
                ->where('mpesa_transaction_id', $id)
                ->first()->meter_id;
        } catch (Throwable $throwable) {
            $meter_id = MeterToken::where('mpesa_transaction_id', $id)->first()->meter_id;
        }
        $user = User::where('meter_id', $meter_id)->first();
        $transaction = MpesaTransaction::where('id', $id)->first();
        return response()->json([
            'user' => $user,
            'transaction' => $transaction
        ]);
    }

    private function filterQuery(Builder $query, Request $request): Builder
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');
        if ($request->has('search') && Str::length($request->query('search')) > 0) {
            $query = $query->where(function ($query) use ($search) {
                $query->where('mpesa_transactions.TransAmount', 'like', '%' . $search . '%');
                $query->orWhere('mpesa_transactions.MSISDN', 'like', '%' . $search . '%');
            });
        }

        if ($request->has('station_id')) {
            $query = $query->where('meter_stations.id', $stationId);
        }

        if ($request->has('user_id')) {
            $query = $query->join('users', 'users.meter_id', 'meters.id');
            $query = $query->where('users.id', $request->query('user_id'));
        }

        if ($request->has('sortBy')) {
            $query = $query->orderBy($sortBy, $sortOrder);
        }

        return $query;
    }
}
