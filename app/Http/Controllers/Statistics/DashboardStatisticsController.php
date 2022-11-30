<?php

namespace App\Http\Controllers\Statistics;

use App\Http\Controllers\Controller;
use App\Models\ConnectionFeePayment;
use App\Models\CreditAccount;
use App\Models\DailyMeterReading;
use App\Models\FaultyMeter;
use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterReading;
use App\Models\MeterStation;
use App\Models\MeterToken;
use App\Models\MpesaTransaction;
use App\Models\UnaccountedDebt;
use App\Models\User;
use App\Services\TransactionService;
use App\Services\TransactionStatisticsService;
use Carbon\Carbon;
use DateTime;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use JsonException;
use Log;
use Throwable;

class DashboardStatisticsController extends Controller
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

    public function totalRevenueSum(TransactionStatisticsService $transactionStatisticsService): JsonResponse
    {
        return response()->json(['revenue' => $transactionStatisticsService->calculateRevenue(null, null)]);
    }

    public function previousMonthRevenueStatistics(TransactionStatisticsService $transactionStatisticsService): JsonResponse
    {
        $firstDayOfPreviousMonth = Carbon::now()->startOfMonth()->subMonthsNoOverflow()->toDateTimeString();
        $lastDayOfPreviousMonth = Carbon::now()->subMonthsNoOverflow()->endOfMonth()->toDateTimeString();

        $firstDayOfBeforeLastMonth = Carbon::now()->startOfMonth()->subMonthsNoOverflow(2)->toDateTimeString();
        $lastDayOfBeforeLastMonth = Carbon::now()->subMonthsNoOverflow(2)->endOfMonth()->toDateTimeString();

        $previousMonthRevenue = $transactionStatisticsService->calculateRevenue($firstDayOfPreviousMonth, $lastDayOfPreviousMonth);
        $monthBeforeLastMonthRevenue = $transactionStatisticsService->calculateRevenue($firstDayOfBeforeLastMonth, $lastDayOfBeforeLastMonth);
        $previousMonthStationRevenue = $transactionStatisticsService->calculateStationRevenue($firstDayOfPreviousMonth, $lastDayOfPreviousMonth);
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

    /**
     * @throws JsonException
     */
    public function  mainMeterReading(Request $request): JsonResponse
    {
        $main_meters = Meter::select('meter_stations.name', 'meters.id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
            ->where('main_meter', true)
            ->get();
        $distinct_months = $this->getDistinctMonths();
        $distinct_years = $this->getDistinctYears();

        $main_meter_readings = [];
        foreach ($main_meters as $main_meter){
            $meter = Meter::find($main_meter->id);
            $readings = [
                'name' => $main_meter->name,
                'readings' => json_decode($this->meterReadings($request, $meter, $distinct_months, $distinct_years)->content(), JSON_THROW_ON_ERROR, 512, JSON_THROW_ON_ERROR)
            ];
            $main_meter_readings[] = $readings;
        }
        return response()->json([
            'main_meter_readings' => $main_meter_readings,
            'per_station_average_readings' => json_decode($this->perStationAverageMeterReading($request)->content(), JSON_THROW_ON_ERROR, 512, JSON_THROW_ON_ERROR)]);
    }

    private function getDistinctMonths(): Collection
    {
        $daily_meter_reading_months = DailyMeterReading::select(DB::raw('DISTINCT MONTH(created_at) as month'), DB::raw('YEAR(created_at) as year'))
            ->orderBy('month', 'asc')
            ->get();

        $meter_reading_months = MeterReading::select(DB::raw('DISTINCT MONTH(month) as month'), DB::raw('YEAR(month) as year'))
            ->orderBy('month', 'asc')
            ->get();

        return $daily_meter_reading_months->concat($meter_reading_months)->unique('month')->sortBy('month');
    }

    private function getDistinctYears(): Collection
    {
        $daily_meter_reading_years = DailyMeterReading::select(DB::raw('DISTINCT YEAR(created_at) as year'))
            ->orderBy('year', 'asc')
            ->get();

        $meter_reading_years = MeterReading::select(DB::raw('DISTINCT YEAR(month) as year'))
            ->orderBy('year', 'asc')
            ->get();

        return $daily_meter_reading_years->concat($meter_reading_years)->unique('year')->sortBy('year');
    }

    /**
     * @throws JsonException
     */
    public function perStationAverageMeterReading(Request $request): JsonResponse
    {
        $meter_stations = MeterStation::all();
        $meter_station_readings = [];
        $commonMonths = new Collection();
        foreach ($meter_stations as $meter_station) {
            if ($request->has('filter')) {
                if ($request->query('filter') === 'last-7-days') {
                    $meter_station_meters = DailyMeterReading::select(DB::raw('SUM(daily_meter_readings.reading) as reading'), DB::raw('DAYNAME(daily_meter_readings.created_at) as name'))
                        ->join('meters', 'meters.id', 'daily_meter_readings.meter_id')
                        ->where('meters.station_id', $meter_station->id)
                        ->where('meters.main_meter', false)
                        ->whereBetween('daily_meter_readings.created_at', [Carbon::now()->subDays(7), Carbon::now()->endOfWeek()])
                        ->groupBy('name', 'reading')
                        ->get();
                } else if ($request->query('filter') === 'monthly') {
                    $meter_station_meters = MeterReading::select(DB::raw('meter_readings.current_reading as reading'), DB::raw('MONTHNAME(meter_readings.month) as name'))
                        ->join('meters', 'meters.id', 'meter_readings.meter_id')
                        ->where('meters.station_id', $meter_station->id)
                        ->where('meters.main_meter', false)
                        ->whereBetween('meter_readings.month', [Carbon::now()->subYear(), Carbon::now()->endOfMonth()])
                        ->groupBy('name', 'reading')
                        ->get();
                } else {
                    $meter_station_meters = MeterReading::select(DB::raw('meter_readings.current_reading as reading'), DB::raw('YEAR(meter_readings.month) as name'))
                        ->join('meters', 'meters.id', 'meter_readings.meter_id')
                        ->where('meters.station_id', $meter_station->id)
                        ->where('meters.main_meter', false)
                        ->whereBetween('meter_readings.month', [Carbon::now()->subYear(), Carbon::now()->endOfMonth()])
                        ->groupBy('name', 'reading')
                        ->get();
                }

            }

            $readingDetails = $this->calculateMeterReadingsSum($meter_station_meters->toArray());
            $meter_station_readings[] = [
                'name' => $meter_station->name,
                'readings' => $readingDetails['revenue']];
            $commonMonths = $commonMonths->merge($readingDetails['common_months']);

        }
        $meter_station_readings = $this->initializeMissingMonths($meter_station_readings, $commonMonths);
        return response()->json($meter_station_readings);
    }

    private function initializeMissingMonths($meter_station_readings, $commonMonths): Collection
    {
        $collection = new Collection();
        foreach ($meter_station_readings as $meter_station_reading) {
            foreach ($commonMonths as $commonMonth) {
                $monthExists = false;
                foreach ($meter_station_reading['readings'] as $meter_station_reading_month) {
                    if ($meter_station_reading_month['label'] === $commonMonth['name']) {
                        $monthExists = true;
                        break;
                    }
                }
                if (!$monthExists) {
                    $meter_station_reading['readings'][] = [
                        'label' => $commonMonth['name'],
                        'reading' => 0.00
                    ];
                }
            }
            $meter_station_reading['readings'] = $this->sortRevenueMonths($meter_station_reading['readings']);
            $collection->push($meter_station_reading);
        }

        return $collection;
    }

    public function sortRevenueMonths($revenue): array
    {
        usort($revenue, static function($a, $b) {
            $a = strtotime($a['label']);
            $b = strtotime($b['label']);
            return $a - $b;
        });
        return $revenue;

    }

    public function meterReadings(
        Request $request,
        Meter $meter,
        Collection $distinct_months,
        Collection $distinct_years): JsonResponse
    {
        if ($request->has('filter')) {
            if ($request->query('filter') === 'last-7-days') {
                return response()->json($this->dayWiseMeterReadings($meter->id));
            }
            if ($request->query('filter') === 'monthly') {
                return response()->json($this->monthWiseMeterReadings($meter->id, $distinct_months));
            }
            if ($request->query('filter') === 'yearly') {
                return response()->json($this->yearWiseMeterReadings($meter->id, $distinct_years));
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

    public function monthWiseMeterReadings($meter_id, $distinct_months): Collection
    {
        $readings = new Collection();
        foreach ($distinct_months as $distinct_month) {
            $latest_reading  = DailyMeterReading::select('reading')
                ->where('meter_id', $meter_id)
                ->whereMonth('created_at', $distinct_month->month)
                ->whereYear('created_at', $distinct_month->year)
                ->latest()
                ->first();
            $readings->push([
                'label' => $this->monthNumberToName($distinct_month->month),
                'reading' => $latest_reading ? $latest_reading->reading : 0.00
            ]);
        }

        return $readings;
    }

    public  function yearWiseMeterReadings($meter_id, $distinct_years): Collection
    {
        $readings = new Collection();
        foreach ($distinct_years as $distinct_year) {
            $latest_reading  = DailyMeterReading::select('reading')
                ->where('meter_id', $meter_id)
                ->whereYear('created_at', $distinct_year->year)
                ->latest()
                ->first();
            $readings->push([
                'label' => $distinct_year->year,
                'reading' => $latest_reading ? $latest_reading->reading : 0.00
            ]);
        }

        return $readings;
    }

    private function monthNumberToName($monthNumber): string
    {
        return DateTime::createFromFormat('!m', $monthNumber)->format('F');
    }

    private function calculateMeterReadingsSum($meterReadings): Collection
    {

        $meterReadings = array_reduce($meterReadings, static function ($accumulator, $meterReading) {
            $accumulator[$meterReading['name']] = $accumulator[$meterReading['name']] ?? 0;
            $accumulator[$meterReading['name']] += $meterReading['reading'];

            return $accumulator;
        });

        if ($meterReadings === null) {
            return collect(['revenue' => [], 'common_months' => collect([])]);
        }
        $stationsRevenue = [];
        $commonMonths = new Collection();
        foreach ($meterReadings as $key => $value) {
            $stationsRevenue[] = [
                'label' => $key,
                'reading' => $value
            ];
            $commonMonths->push([
                'name' => $key
            ]);
        }

        return collect(['revenue' => $stationsRevenue,
            'common_months' => $commonMonths]);
    }

}
