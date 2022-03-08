<?php

namespace App\Http\Controllers;

use App\Models\MeterBilling;
use App\Models\MeterToken;
use App\Models\MpesaTransaction;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $meter_billings_transactions = MeterBilling::query();
        $meter_billings_transactions = $this->getTransactions($meter_billings_transactions, $request, 'billing');

        $meter_tokens_transactions = MeterToken::query();
        $meter_tokens_transactions = $this->getTransactions($meter_tokens_transactions, $request, 'tokens');

        $all_transactions = $meter_billings_transactions->merge($meter_tokens_transactions);

        return response()->json($all_transactions);
    }

    public function unresolvedTransactionIndex(): JsonResponse
    {
        return response()->json(UnresolvedMpesaTransaction::select('unresolved_mpesa_transactions.reason', 'mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.BillRefNumber as account_number', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'unresolved_mpesa_transactions.mpesa_transaction_id')
            ->latest('transaction_time')
            ->get());
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

    /**
     * @param Builder $transactions
     * @param Request $request
     * @param $type
     * @return void
     */
    public function getTransactions(Builder $transactions, Request $request, $type): Collection
    {
        if ($type === 'billing') {
            $transactions = $transactions->select('mpesa_transactions.id', 'meter_billings.mpesa_transaction_id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time')
                ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id')
                ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
                ->join('meters', 'meters.id', 'meter_readings.meter_id');
        } else {
            $transactions = $transactions->select('mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time')
                ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
                ->join('meters', 'meters.id', 'meter_tokens.meter_id');
        }

        $transactions = $transactions->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        if ($request->has('station_id')) {
            $transactions = $transactions->where('meter_stations.id', $request->query('station_id'));
        }
        if ($request->has('user_id')) {
            $transactions = $transactions->join('users', 'users.meter_id', 'meters.id');
            $transactions = $transactions->where('users.id', $request->query('user_id'));
        }
        return $transactions
            ->latest('transaction_time')
            ->get();
    }
}
