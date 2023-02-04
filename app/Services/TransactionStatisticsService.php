<?php

namespace App\Services;

use App\Models\ConnectionFeePayment;
use App\Models\CreditAccount;
use App\Models\MeterBilling;
use App\Models\MeterStation;
use App\Models\MeterToken;
use App\Models\MpesaTransaction;
use App\Models\UnaccountedDebt;
use Carbon\Carbon;
use Date;
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
        $meterBilling = MeterBilling::select('mpesa_transactions.id as id', 'meter_stations.id as meter_station_id', 'mpesa_transactions.TransAmount as amount')
            ->join('mpesa_transactions', 'meter_billings.mpesa_transaction_id', 'mpesa_transactions.id')
            ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->when($from !== null && $to !== null, function ($query) use ($from, $to) {
                return $query->whereBetween('mpesa_transactions.created_at', [$from, $to]);
            })
            ->when($meterStation !== null, function ($query) use ($meterStation) {
                return $query->where('meter_stations.id', $meterStation->id);
            });

        $meterToken = MeterToken::select('mpesa_transactions.id as id', 'meter_stations.id as meter_station_id', 'mpesa_transactions.TransAmount as amount')
            ->join('mpesa_transactions', 'meter_tokens.mpesa_transaction_id', 'mpesa_transactions.id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->when($from !== null && $to !== null, function ($query) use ($from, $to) {
                return $query->whereBetween('mpesa_transactions.created_at', [$from, $to]);
            })
            ->when($meterStation !== null, function ($query) use ($meterStation) {
                return $query->where('meter_stations.id', $meterStation->id);
            });

        $connectionFeePayment = ConnectionFeePayment::select('mpesa_transactions.id as id', 'meter_stations.id as meter_station_id', 'mpesa_transactions.TransAmount as amount')
            ->join('mpesa_transactions', 'connection_fee_payments.mpesa_transaction_id', 'mpesa_transactions.id')
            ->join('connection_fees', 'connection_fees.id', 'connection_fee_payments.connection_fee_id')
            ->join('users', 'users.id', 'connection_fees.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->when($from !== null && $to !== null, function ($query) use ($from, $to) {
                return $query->whereBetween('mpesa_transactions.created_at', [$from, $to]);
            })
            ->when($meterStation !== null, function ($query) use ($meterStation) {
                return $query->where('meter_stations.id', $meterStation->id);
            });

        $creditAccount = CreditAccount::select('mpesa_transactions.id as id', 'meter_stations.id as meter_station_id', 'mpesa_transactions.TransAmount as amount')
            ->join('mpesa_transactions', 'credit_accounts.mpesa_transaction_id', 'mpesa_transactions.id')
            ->join('users', 'users.id', 'credit_accounts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->when($from !== null && $to !== null, function ($query) use ($from, $to) {
                return $query->whereBetween('mpesa_transactions.created_at', [$from, $to]);
            })
            ->when($meterStation !== null, function ($query) use ($meterStation) {
                return $query->where('meter_stations.id', $meterStation->id);
            });

        $unaccountedDebt = UnaccountedDebt::select('mpesa_transactions.id as id', 'meter_stations.id as meter_station_id', 'mpesa_transactions.TransAmount as amount')
            ->join('mpesa_transactions', 'unaccounted_debts.mpesa_transaction_id', 'mpesa_transactions.id')
            ->join('users', 'users.id', 'unaccounted_debts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->when($from !== null && $to !== null, function ($query) use ($from, $to) {
                return $query->whereBetween('mpesa_transactions.created_at', [$from, $to]);
            })
            ->when($meterStation !== null, function ($query) use ($meterStation) {
                return $query->where('meter_stations.id', $meterStation->id);
            });

        $meterBilling->union($meterToken);
        $meterBilling->union($connectionFeePayment);
        $meterBilling->union($creditAccount);
        $meterBilling->union($unaccountedDebt);

        return $meterBilling->sum('amount');
    }

    public function  getMonthlyRevenueStatistics(string $meterStationId = null, int $year = null): array
    {
        if ($year === null) {
            $year = (int) date('Y');
        }
        $monthWiseTransactionDetails = $this->getMonthWiseTransactionDetails($year, $meterStationId);
        $revenueSum = $this->calculateRevenueSum($monthWiseTransactionDetails);

        return $this->sortRevenueMonths($revenueSum);
    }

    public function getRevenueYears(): array
    {
        $years = [];
        $models = [
            MeterBilling::class,
            MeterToken::class,
            ConnectionFeePayment::class,
            CreditAccount::class,
            UnaccountedDebt::class
        ];

        foreach ($models as $model) {
            $years = array_unique(
                array_merge(
                    $years,
                    $model::select(DB::raw('YEAR(created_at) as year'))
                        ->groupBy('year')
                        ->orderBy('year', 'desc')
                        ->pluck('year')
                        ->toArray()
                )
            );
        }

        return array_unique($years);
    }

    public function getMonthlyRevenueStatisticsPerStation(Collection $stations, int $year = null): array
    {
        $stationsRevenue = new Collection();
        $commonMonths = new Collection();
        if ($year === null) {
            $year = (int) date('Y');
        }
        foreach ($stations as $station) {
            $monthlyRevenueStatistics = $this->getMonthlyRevenueStatistics($station->id, $year);
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

    public function getMonthWiseTransactionDetails(string $year, string $meterStationId = null)
    {
        $meterBilling = MeterBilling::select('mpesa_transactions.id as id', 'mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id')
            ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->whereYear('mpesa_transactions.created_at', $year)
            ->when($meterStationId !== null, function ($query) use ($meterStationId) {
                return $query->where('meter_stations.id', $meterStationId);
            });

        $meterToken = MeterToken::select('mpesa_transactions.id as id', 'mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->whereYear('mpesa_transactions.created_at', $year)
            ->when($meterStationId !== null, function ($query) use ($meterStationId) {
                return $query->where('meter_stations.id', $meterStationId);
            });

        $connectionFeePayment = ConnectionFeePayment::select('mpesa_transactions.id as id', 'mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'connection_fee_payments.mpesa_transaction_id')
            ->join('connection_fees', 'connection_fees.id', 'connection_fee_payments.connection_fee_id')
            ->join('users', 'users.id', 'connection_fees.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->whereYear('mpesa_transactions.created_at', $year)
            ->when($meterStationId !== null, function ($query) use ($meterStationId) {
                return $query->where('meter_stations.id', $meterStationId);
            });

        $creditAccount = CreditAccount::select('mpesa_transactions.id as id', 'mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'credit_accounts.mpesa_transaction_id')
            ->join('users', 'users.id', 'credit_accounts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->whereYear('mpesa_transactions.created_at', $year)
            ->when($meterStationId !== null, function ($query) use ($meterStationId) {
                return $query->where('meter_stations.id', $meterStationId);
            });

        $unaccountedDebt = UnaccountedDebt::select('mpesa_transactions.id as id', 'mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'unaccounted_debts.mpesa_transaction_id')
            ->join('users', 'users.id', 'unaccounted_debts.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->whereYear('mpesa_transactions.created_at', $year)
            ->when($meterStationId !== null, function ($query) use ($meterStationId) {
                return $query->where('meter_stations.id', $meterStationId);
            });

        $meterBilling->union($meterToken);
        $meterBilling->union($connectionFeePayment);
        $meterBilling->union($creditAccount);
        $meterBilling->union($unaccountedDebt);

        return $meterBilling->get();

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
