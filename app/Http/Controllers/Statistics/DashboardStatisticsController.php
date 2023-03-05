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

    public function index(TransactionStatisticsService $transactionStatisticsService): JsonResponse
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
            'faulty_meters' => $faultyMeters,
            'revenue' => $transactionStatisticsService->calculateRevenue(null, null)
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

    public function  getMeterReadingStatistics(Request $request, Meter $meter): JsonResponse
    {
        $distinct_months = $this->getDistinctMonths();
        $distinct_years = $this->getDistinctYears();

        return $this->meterReadings($request, $meter, $distinct_months, $distinct_years);
    }

    /**
     */
    public function  mainMeterReading(Request $request): JsonResponse
    {
         $startDate = $request->input('fromDate');
         $endDate = $request->input('toDate');

        $meter_stations = MeterStation::with('mainMeter')
            ->get();

        $meter_readings = [];
        foreach ($meter_stations as $meter_station) {
            $main_meter_readings = [];
            if ($meter_station->mainMeter) {
                $main_meter_readings = $this->getDailyMeterReadingsChartData($startDate, $endDate, $meter_station->mainMeter->id);
            }
            $meter_readings[] = [
                'station' => $meter_station->name,
                'main_meter_readings' => $main_meter_readings,
                'meters_reading' => $this->getDailyMeterReadingsChartData($startDate, $endDate, null, $meter_station->id),
            ];
        }
        return response()->json($meter_readings);
    }


    /**
     * @param string $startDate
     * @param string $endDate
     * @param string|null $meterId
     * @param string|null $meterStationId
     * @return array
     */
    private function getDailyMeterReadingsChartData(string $startDate, string $endDate, string $meterId = null, string $meterStationId = null): array
    {
        $dailyMeterReadings = DB::table('daily_meter_readings')
            ->select('meter_id', DB::raw('DATE(daily_meter_readings.created_at) as date'), DB::raw('AVG(reading) as average_reading'))
            ->whereBetween('daily_meter_readings.created_at', [$startDate, $endDate])
            ->groupBy('meter_id', 'date')
            ->orderBy('date');
        $dailyMeterReadings->when($meterId, static function ($query) use ($meterId) {
            return $query->where('meter_id', $meterId);
        });
        $dailyMeterReadings->when($meterStationId, static function ($query) use ($meterStationId) {
            return $query->join('meters', 'meters.id', '=', 'daily_meter_readings.meter_id')
                ->where('meters.station_id', $meterStationId);
        });
        $dailyMeterReadings = $dailyMeterReadings->get();

        // Calculate the daily consumption of each meter
        $dailyConsumption = [];
        $prevReadings = [];
        foreach ($dailyMeterReadings as $reading) {
            $meterId = $reading->meter_id;
            if (isset($prevReadings[$meterId])) {
                $consumption = $reading->average_reading - $prevReadings[$meterId];
                $dailyConsumption[$reading->date][$meterId] = $consumption;
            }
            $prevReadings[$meterId] = $reading->average_reading;
        }

        // Calculate the time span for each chart data point
        $numDays = count($dailyConsumption);
        $timeSpan = max(1, round($numDays / 12));
        $chartData = [];
        $currentSum = 0;
        $currentCount = 0;
        $currentIndex = 0;
        foreach ($dailyConsumption as $date => $consumptions) {
            $currentSum += array_sum($consumptions);
            $currentCount += count($consumptions);
            $currentIndex++;
            if ($currentIndex % $timeSpan === 0 || $currentIndex === $numDays) {
                $average = $currentSum / max(1, $currentCount);
                $chartData[] = [
                    'x' => strtotime($date) * 1000,
                    'y' => round($average),
                ];
                $currentSum = 0;
                $currentCount = 0;
            }
        }
        return $chartData;
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

}
