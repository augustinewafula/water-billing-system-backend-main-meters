<?php

namespace App\Http\Controllers;

use App\Models\ConnectionFeePayment;
use App\Models\CreditAccount;
use App\Models\DailyMeterReading;
use App\Models\FaultyMeter;
use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterReading;
use App\Models\MeterStation;
use App\Models\MeterToken;
use App\Models\MonthlyServiceChargePayment;
use App\Models\MpesaTransaction;
use App\Models\UnaccountedDebt;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use JsonException;
use Log;
use PhpParser\Node\Expr\Cast\Double;
use Str;
use Throwable;

class StatisticsController extends Controller
{
    public function __construct()
    {
//        $this->middleware(['role:super-admin|admin|supervisor']);
    }

    public function index(): JsonResponse
    {
        try {
            $users = User::role('user')
                ->count();
            $mainMeters = Meter::where('main_meter', true)
                ->count();
            $meters = Meter::where('main_meter', false)
                ->count();
            $faultyMeters = FaultyMeter::count();

        } catch (Throwable $throwable) {
            Log::error($throwable);
            $response = ['message' => 'Something went wrong'];
            return response()->json($response, 422);
        }

        return response()->json([
            'users' => $users,
            'main_meters' => $mainMeters,
            'meters' => $meters,
            'faulty_meters' => $faultyMeters
        ]);
    }

    public function totalRevenueSum(): JsonResponse
    {
        return response()->json(['revenue' => $this->calculateRevenue(null, null)]);
    }

    public function previousMonthRevenueStatistics(): JsonResponse
    {
        $firstDayOfPreviousMonth = Carbon::now()->startOfMonth()->subMonthsNoOverflow()->toDateTimeString();
        $lastDayOfPreviousMonth = Carbon::now()->subMonthsNoOverflow()->endOfMonth()->toDateTimeString();

        $firstDayOfBeforeLastMonth = Carbon::now()->startOfMonth()->subMonthsNoOverflow(2)->toDateTimeString();
        $lastDayOfBeforeLastMonth = Carbon::now()->subMonthsNoOverflow(2)->endOfMonth()->toDateTimeString();

        $previousMonthRevenue = $this->calculateRevenue($firstDayOfPreviousMonth, $lastDayOfPreviousMonth);
        $monthBeforeLastMonthRevenue = $this->calculateRevenue($firstDayOfBeforeLastMonth, $lastDayOfBeforeLastMonth);
        $previousMonthStationRevenue = $this->calculateStationRevenue($firstDayOfPreviousMonth, $lastDayOfPreviousMonth);
        $stations = MeterStation::pluck('name')
            ->all();

        return response()->json([
            'previousMonthRevenue' => [
                'month_name' => Carbon::now()->startOfMonth()->subMonthsNoOverflow()->isoFormat('MMMM'),
                'value' => $previousMonthRevenue],
            'monthBeforeLastMonthRevenue' => $monthBeforeLastMonthRevenue,
            'previousMonthStationRevenue' => $previousMonthStationRevenue,
            'stations' => $stations
        ]);

    }

    public function monthlyRevenueStatistics(): JsonResponse
    {
        $mpesaTransactions = $this->getMpesaTransactions();
        $monthWiseRevenue = new Collection();
        foreach ($mpesaTransactions as $mpesaTransaction) {
            $monthWiseTransactionDetails = $this->getMonthWiseTransactionDetails($mpesaTransaction->id);
            if ($monthWiseTransactionDetails) {
                $monthWiseRevenue = $monthWiseRevenue->merge($monthWiseTransactionDetails);
            }
        }
        $revenueSum = $this->calculateRevenueSum($monthWiseRevenue);
        $sortedRevenueSum = $this->sortRevenueMonths($revenueSum);
        return response()->json($sortedRevenueSum);

    }

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

    public function getMonthWiseTransactionDetails(string $transactionId)
    {
        $meterBilling = MeterBilling::select('mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id')
            ->join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('meter_billings.mpesa_transaction_id', $transactionId)
            ->whereYear('mpesa_transactions.created_at', date('Y'))
            ->distinct('name')
            ->groupBy('name', 'total')
            ->get();
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
            ->groupBy('name', 'total')
            ->get();
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
            ->groupBy('name', 'total')
            ->get();
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
            ->groupBy('name', 'total')
            ->get();
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
            ->groupBy('name', 'total')
            ->get();
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
     * @throws JsonException
     */
    public function  mainMeterReading(Request $request): JsonResponse
    {
        $main_meters = Meter::select('meter_stations.name', 'meters.id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('main_meter', true)
            ->get();
        $main_meter_readings = [];
        foreach ($main_meters as $main_meter){
            $meter = Meter::find($main_meter->id);
            $readings = [
                'name' => $main_meter->name,
                'readings' => json_decode($this->meterReadings($request, $meter)->content(), JSON_THROW_ON_ERROR, 512, JSON_THROW_ON_ERROR)
            ];
            $main_meter_readings[] = $readings;
        }
        return response()->json([
            'main_meter_readings' => $main_meter_readings,
            'per_station_average_readings' => json_decode($this->perStationAverageMeterReading($request)->content(), JSON_THROW_ON_ERROR, 512, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @throws JsonException
     */
    public function perStationAverageMeterReading(Request $request): JsonResponse
    {
        $meter_stations = MeterStation::all();
        $meter_station_readings = [];
        foreach ($meter_stations as $meter_station){
            $per_station_meter_readings = [];
            $meter_station_meters = Meter::select('id')
                ->where('station_id', $meter_station->id)
                ->where('main_meter', false)
                ->get();

            foreach ($meter_station_meters as $meter_station_meter){
                $meter_reading = DailyMeterReading::where('meter_id', $meter_station_meter->id);
                if ($request->has('filter')) {
                    if ($request->query('filter') === 'last-7-days') {
                        $meter_reading = $meter_reading->whereBetween('created_at', [Carbon::now()->subDays(7), Carbon::now()->endOfWeek()])
                            ->whereYear('created_at', date('Y'));
                    }
                    if ($request->query('filter') === 'monthly') {
                        $meter_reading = $meter_reading->whereBetween('created_at', [Carbon::now()->subDays(7), Carbon::now()->endOfWeek()])
                            ->whereYear('created_at', date('Y'));
                    }
                }
                $meter_reading = $meter_reading->groupBy('name', 'reading')
                    ->select('reading', DB::raw('DAYNAME(created_at) as name'))
                    ->get();
                $per_station_meter_readings[] = $meter_reading;


            }
            $meter_station_readings[] = [
                'name' => $meter_station->name,
                'readings' => $this->calculateMeterReadingsSum( $per_station_meter_readings)];

        }
        return response()->json($meter_station_readings);
    }

    public function meterReadings(Request $request, Meter $meter): JsonResponse
    {
        if ($request->has('filter')) {
            if ($request->query('filter') === 'last-7-days') {
                return response()->json($this->dayWiseMeterReadings($meter->id));
            }
            if ($request->query('filter') === 'monthly') {
                return response()->json($this->monthWiseMeterReadings($meter->id));
            }
        }
        return response()->json([]);
    }

    public function dayWiseMeterReadings($meter_id): Collection
    {
        return DailyMeterReading::select('reading', DB::raw('DAYNAME(created_at) as label'))
            ->whereBetween('created_at', [Carbon::now()->subDays(7), Carbon::now()->endOfWeek()])
            ->whereYear('created_at', date('Y'))
            ->where('meter_id', $meter_id)
            ->oldest()
            ->groupBy('label', 'reading')
            ->get();
    }

    public function monthWiseMeterReadings($meter_id): Collection
    {
        return MeterReading::select('current_reading as reading', DB::raw('MONTHNAME(month) as label'))
            ->whereYear('month', date('Y'))
            ->where('meter_id', $meter_id)
            ->distinct('label')
            ->oldest()
            ->groupBy('label', 'reading')
            ->get();
    }

    /**
     * @param $monthWiseRevenue
     * @return array
     */
    private function calculateRevenueSum($monthWiseRevenue): array
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

    private function calculateMeterReadingsSum($meterReadings): array
    {

        $meterReadings = array_reduce($meterReadings, static function ($accumulator, $item) {
            foreach ($item as $meterReading){
                $accumulator[$meterReading['name']] = $accumulator[$meterReading['name']] ?? 0;
                $accumulator[$meterReading['name']] += $meterReading['reading'];
            }
            return $accumulator;
        });

        if ($meterReadings === null) {
            return [];
        }
        $stationsRevenue = [];
        foreach ($meterReadings as $key => $value) {
            $stationsRevenue[] = [
                'label' => $key,
                'reading' => $value
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
    private function getMpesaTransactions(string $from=null, string $to=null): Collection
    {
        $mpesaTransactions = MpesaTransaction::query();
        if ($from !== null && $to !== null) {
            $mpesaTransactions = $mpesaTransactions->whereBetween('created_at', [$from, $to]);
        }
        return $mpesaTransactions->get();
    }

}
