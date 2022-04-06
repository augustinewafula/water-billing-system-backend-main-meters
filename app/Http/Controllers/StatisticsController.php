<?php

namespace App\Http\Controllers;

use App\Models\DailyMeterReading;
use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterReading;
use App\Models\MeterStation;
use App\Models\MeterToken;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use Throwable;

class StatisticsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:super-admin|admin|supervisor']);
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

        } catch (Throwable $throwable) {
            Log::error($throwable);
            $response = ['message' => 'Something went wrong'];
            return response()->json($response, 422);
        }

        return response()->json([
            'users' => $users,
            'main_meters' => $mainMeters,
            'meters' => $meters,
            'revenue' => $this->calculateEarnings(null, null)
        ]);
    }

    public function monthlyEarnings(): JsonResponse
    {
        $firstDayOfPreviousMonth = Carbon::now()->startOfMonth()->subMonthsNoOverflow()->toDateString();
        $lastDayOfPreviousMonth = Carbon::now()->subMonthsNoOverflow()->endOfMonth()->toDateString();

        $firstDayOfBeforeLastMonth = Carbon::now()->startOfMonth()->subMonthsNoOverflow(2)->toDateString();
        $lastDayOfBeforeLastMonth = Carbon::now()->subMonthsNoOverflow(2)->endOfMonth()->toDateString();

        $previousMonthEarnings = $this->calculateEarnings($firstDayOfPreviousMonth, $lastDayOfPreviousMonth);
        $monthBeforeLastMonthEarnings = $this->calculateEarnings($firstDayOfBeforeLastMonth, $lastDayOfBeforeLastMonth);
        $previousMonthStationEarnings = $this->calculateStationEarnings($firstDayOfPreviousMonth, $lastDayOfPreviousMonth);
        $stations = MeterStation::pluck('name')
            ->all();

        return response()->json([
            'previousMonthEarnings' => [
                'month_name' => Carbon::now()->startOfMonth()->subMonthsNoOverflow()->isoFormat('MMMM'),
                'value' => $previousMonthEarnings],
            'monthBeforeLastMonthEarnings' => $monthBeforeLastMonthEarnings,
            'previousMonthStationEarnings' => $previousMonthStationEarnings,
            'stations' => $stations
        ]);

    }

    public function calculateStationEarnings(?string $from, ?string $to): array
    {
        $billingsSum = MeterBilling::join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('meter_stations', 'meters.station_id', 'meter_stations.id');
        if ($from !== null && $to !== null) {
            $billingsSum = $billingsSum->where('meter_billings.created_at', '>', $from)
                ->where('meter_billings.created_at', '<', $to);
        }
        $billingsSum = $billingsSum->groupBy('name')
            ->selectRaw('sum(meter_billings.amount_paid) as total, meter_stations.name')
            ->get();

        $tokenSum = MeterToken::join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('meter_stations', 'meters.station_id', 'meter_stations.id');
        if ($from !== null && $to !== null) {
            $tokenSum = $tokenSum->where('meter_tokens.created_at', '>', $from)
                ->where('meter_tokens.created_at', '<', $to);
        }
        $tokenSum = $tokenSum->groupBy('name')
            ->selectRaw('sum(mpesa_transactions.TransAmount) as total, meter_stations.name')
            ->get();
        $all = $billingsSum->concat($tokenSum)->toArray();

        $all = array_reduce($all, static function ($accumulator, $item) {
            $accumulator[$item['name']] = $accumulator[$item['name']] ?? 0;
            $accumulator[$item['name']] += $item['total'];
            return $accumulator;
        });
        if ($all === null) {
            return [];
        }
        $stationsEarning = [];
        foreach ($all as $key => $value) {
            $stationsEarning[] = [
                'name' => $key,
                'value' => $value
            ];
        }
        return $stationsEarning;
    }

    public function sumStationEarnings($accumulator, $item)
    {
        $accumulator[$item['name']] = $accumulator[$item['name']] ?? 0;
        $accumulator[$item['name']] += $item['total'];
        return $accumulator;
    }

    public function calculateEarnings(?string $from, ?string $to)
    {
        $billingsSum = MeterBilling::select('meter_billings.*', 'mpesa_transactions.TransAmount')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id');
        if ($from !== null && $to !== null) {
            $billingsSum = $billingsSum->where('mpesa_transactions.created_at', '>', $from)
                ->where('mpesa_transactions.created_at', '<', $to);
        }
        $billingsSum = $billingsSum->sum('amount_paid');

        $tokenSum = MeterToken::select('meter_tokens.*', 'mpesa_transactions.TransAmount')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id');
        if ($from !== null && $to !== null) {
            $tokenSum = $tokenSum->where('mpesa_transactions.created_at', '>', $from)
                ->where('mpesa_transactions.created_at', '<', $to);
        }
        $tokenSum = $tokenSum->sum('TransAmount');

        return $billingsSum + $tokenSum;
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

    public function dayWiseMeterReadings($meter_id)
    {
        return DailyMeterReading::select('reading', DB::raw('DAYNAME(created_at) as label'))
            ->whereBetween('created_at', [Carbon::now()->subDays(7), Carbon::now()->endOfWeek()])
            ->whereYear('created_at', date('Y'))
            ->where('meter_id', $meter_id)
            ->oldest()
            ->groupBy('label', 'reading')
            ->get();
    }

    public function monthWiseMeterReadings($meter_id)
    {
        return MeterReading::select('current_reading as reading', DB::raw('MONTHNAME(month) as label'))
            ->whereYear('month', date('Y'))
            ->where('meter_id', $meter_id)
            ->distinct('label')
            ->oldest()
            ->groupBy('label', 'reading')
            ->get();
    }
}
