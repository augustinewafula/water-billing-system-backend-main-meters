<?php

namespace App\Services;

use App\Models\ConnectionFeePayment;
use App\Models\CreditAccount;
use App\Models\MeterBilling;
use App\Models\MeterStation;
use App\Models\MeterToken;
use App\Models\MpesaTransaction;
use App\Models\UnaccountedDebt;
use Illuminate\Support\Collection;
use DB;

class TransactionStatisticsService {

    public function calculateStationRevenue(?string $from, ?string $to): array
    {
        $stations = MeterStation::select('id', 'name')->get();
        $stationsRevenue = [];

        foreach ($stations as $station) {
            $revenue = $this->calculateRevenue($from, $to, $station);
            $stationsRevenue[] = [
                'name' => $station->name,
                'value' => $revenue
            ];
        }

        return $stationsRevenue;
    }

    public function calculateRevenue(?string $from, ?string $to, MeterStation $meterStation = null): Float
    {
        $mpesaTransactions = $this->getMpesaTransactions($from, $to);

        $sum = 0.00;
        foreach ($mpesaTransactions as $mpesaTransaction) {
            $transactionDetails = $this->getTransactionDetails($mpesaTransaction->id);
            if ($transactionDetails) {
                if ($meterStation && $transactionDetails->meter_station_id !== $meterStation->id) {
                    continue;
                }
                $sum += $mpesaTransaction->TransAmount;
            }
        }

        return $sum;
    }

    public function getMonthlyRevenueStatistics(string $meterStationId = null): array
    {
        $mpesaTransactions = $this->getMpesaTransactions();
        $monthWiseRevenue = new Collection();
        foreach ($mpesaTransactions as $mpesaTransaction) {
            $monthWiseTransactionDetails = $this->getMonthWiseTransactionDetails($mpesaTransaction->id, $meterStationId);
            if ($monthWiseTransactionDetails) {
                $monthWiseRevenue = $monthWiseRevenue->merge($monthWiseTransactionDetails);
            }
        }
        $revenueSum = $this->calculateRevenueSum($monthWiseRevenue);

        return $this->sortRevenueMonths($revenueSum);
    }

    public function getMonthlyRevenueStatisticsPerStation(Collection $stations): array
    {
        $stationsRevenue = new Collection();
        $commonMonths = new Collection();
        foreach ($stations as $station) {
            $monthlyRevenueStatistics = $this->getMonthlyRevenueStatistics($station->id);
            $stationsRevenue->push([
                'name' => $station->name,
                'data' => $monthlyRevenueStatistics
            ]) ;

            foreach ($monthlyRevenueStatistics as $monthlyRevenueStatistic) {
                $commonMonths->push([
                    'name' => $monthlyRevenueStatistic['name']
                ]);
            }
        }
        $commonMonths = $commonMonths->unique('name');
        $stationsRevenue = $this->initializeMissingMonths($stationsRevenue, $commonMonths);

        return $stationsRevenue->toArray();
    }

    private function initializeMissingMonths($stationsRevenue, $commonMonths): Collection
    {
        $collection = new Collection();
        foreach ($stationsRevenue as $stationRevenue) {
            foreach ($commonMonths as $commonMonth) {
                $monthExists = false;
                foreach ($stationRevenue['data'] as $stationRevenueMonth) {
                    if ($stationRevenueMonth['name'] === $commonMonth['name']) {
                        $monthExists = true;
                        break;
                    }
                }
                if (!$monthExists) {
                    $stationRevenue['data'][] = [
                        'name' => $commonMonth['name'],
                        'value' => 0.00
                    ];
                }
            }
            $stationRevenue['data'] = $this->sortRevenueMonths($stationRevenue['data']);
            $collection->push($stationRevenue);
        }

        return $collection;
    }

    public function getMonthWiseTransactionDetails(string $transactionId, string $meterStationId = null)
    {
        $meterBilling = MeterBilling::select('mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id')
            ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('meter_billings.mpesa_transaction_id', $transactionId)
            ->whereYear('mpesa_transactions.created_at', date('Y'))
            ->distinct('name')
            ->groupBy('name', 'total');
        if ($meterStationId) {
            $meterBilling = $meterBilling->where('meter_stations.id', $meterStationId);
        }
        $meterBilling = $meterBilling->get();
        if ($meterBilling->count() > 0) {
            return $meterBilling;
        }
        $meterToken = MeterToken::select('mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('meter_tokens.mpesa_transaction_id', $transactionId)
            ->whereYear('mpesa_transactions.created_at', date('Y'))
            ->distinct('name')
            ->groupBy('name', 'total');
        if ($meterStationId) {
            $meterToken = $meterToken->where('meter_stations.id', $meterStationId);
        }
        $meterToken = $meterToken->get();
        if ($meterToken->count() > 0) {
            return $meterToken;
        }
        $connectionFeePayment = ConnectionFeePayment::select('mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'connection_fee_payments.mpesa_transaction_id')
            ->join('connection_fees', 'connection_fees.id', 'connection_fee_payments.connection_fee_id')
            ->join('users', 'users.id', 'connection_fees.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('connection_fee_payments.mpesa_transaction_id', $transactionId)
            ->whereYear('mpesa_transactions.created_at', date('Y'))
            ->distinct('name')
            ->groupBy('name', 'total');
        if ($meterStationId) {
            $connectionFeePayment = $connectionFeePayment->where('meter_stations.id', $meterStationId);
        }
        $connectionFeePayment = $connectionFeePayment->get();
        if ($connectionFeePayment->count() > 0) {
            return $connectionFeePayment;
        }
        $creditAccount = CreditAccount::select('mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'credit_accounts.mpesa_transaction_id')
            ->join('users', 'users.id', 'credit_accounts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('credit_accounts.mpesa_transaction_id', $transactionId)
            ->whereYear('mpesa_transactions.created_at', date('Y'))
            ->distinct('name')
            ->groupBy('name', 'total');
        if ($meterStationId) {
            $creditAccount = $creditAccount->where('meter_stations.id', $meterStationId);
        }
        $creditAccount = $creditAccount->get();
        if ($creditAccount->count() > 0) {
            return $creditAccount;
        }
        $unaccountedDebt = UnaccountedDebt::select('mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'unaccounted_debts.mpesa_transaction_id')
            ->join('users', 'users.id', 'unaccounted_debts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('unaccounted_debts.mpesa_transaction_id', $transactionId)
            ->whereYear('mpesa_transactions.created_at', date('Y'))
            ->distinct('name')
            ->groupBy('name', 'total');
        if ($meterStationId) {
            $unaccountedDebt = $unaccountedDebt->where('meter_stations.id', $meterStationId);
        }
        $unaccountedDebt = $unaccountedDebt->get();
        if ($unaccountedDebt->count() > 0) {
            return $unaccountedDebt;
        }
        return null;
    }

    public function getTransactionDetails(string $transactionId)
    {
        $meterBilling = MeterBilling::select('meter_stations.id as meter_station_id')
            ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('mpesa_transaction_id', $transactionId)
            ->first();
        if ($meterBilling) {
            return $meterBilling;
        }
        $meterToken = MeterToken::select('meter_stations.id as meter_station_id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('mpesa_transaction_id', $transactionId)
            ->first();
        if ($meterToken) {
            return $meterToken;
        }
        $connectionFeePayment = ConnectionFeePayment::select('meter_stations.id as meter_station_id')
            ->join('connection_fees', 'connection_fees.id', 'connection_fee_payments.connection_fee_id')
            ->join('users', 'users.id', 'connection_fees.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('mpesa_transaction_id', $transactionId)
            ->first();
        if ($connectionFeePayment) {
            return $connectionFeePayment;
        }
        $creditAccount = CreditAccount::select('meter_stations.id as meter_station_id')
            ->join('users', 'users.id', 'credit_accounts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('mpesa_transaction_id', $transactionId)
            ->first();
        if ($creditAccount) {
            return $creditAccount;
        }
        $unaccountedDebt = UnaccountedDebt::select('meter_stations.id as meter_station_id')
            ->join('users', 'users.id', 'unaccounted_debts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('mpesa_transaction_id', $transactionId)
            ->first();
        if ($unaccountedDebt) {
            return $unaccountedDebt;
        }
        return null;
    }

    /**
     * @param $monthWiseRevenue
     * @return array
     */
    public function calculateRevenueSum($monthWiseRevenue): array
    {
        $monthWiseRevenue = $monthWiseRevenue->toArray();
        $monthWiseRevenue = array_reduce($monthWiseRevenue, static function ($accumulator, $item) {
            $accumulator[$item['name']] = $accumulator[$item['name']] ?? 0;
            $accumulator[$item['name']] += $item['total'];
            return $accumulator;
        });

        if ($monthWiseRevenue === null) {
            return [];
        }
        $stationsRevenue = [];
        foreach ($monthWiseRevenue as $key => $value) {
            $stationsRevenue[] = [
                'name' => $key,
                'value' => $value
            ];
        }
        return $stationsRevenue;
    }

    public function sortRevenueMonths($revenue): array
    {
        usort($revenue, static function($a, $b) {
            $a = strtotime($a['name']);
            $b = strtotime($b['name']);
            return $a - $b;
        });
        return $revenue;

    }

    /**
     * @param string|null $from
     * @param string|null $to
     * @return Collection
     */
    public function getMpesaTransactions(string $from=null, string $to=null): Collection
    {
        $mpesaTransactions = MpesaTransaction::query();
        if ($from !== null && $to !== null) {
            $mpesaTransactions = $mpesaTransactions->whereBetween('created_at', [$from, $to]);
        }
        return $mpesaTransactions->get();
    }
}
