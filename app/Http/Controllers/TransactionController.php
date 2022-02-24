<?php

namespace App\Http\Controllers;

use App\Models\MeterBilling;
use App\Models\MeterToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        return $transactions->get();
    }
}
