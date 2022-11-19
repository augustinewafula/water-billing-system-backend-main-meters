<?php

namespace App\Http\Controllers\Statistics;

use App\Http\Controllers\Controller;
use App\Models\MeterStation;
use App\Services\TransactionStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TransactionStatisticsController extends Controller
{
    public function todayRevenue(
        Request $request,
        TransactionStatisticsService $transactionStatisticsService): JsonResponse
    {
        $stationId = $request->station_id;
        $station = null;
        if ($stationId) {
            $station = MeterStation::find($stationId);
        }

        $startOfDay = now()->startOfDay();
        $endOfDay = now()->endOfDay();

        $startOfDayYesterday = now()->subDay()->startOfDay();
        $endOfDayYesterday = now()->subDay()->endOfDay();

        $todayRevenue = $transactionStatisticsService->calculateRevenue($startOfDay, $endOfDay, $station);
        $yesterdayRevenue = $transactionStatisticsService->calculateRevenue($startOfDayYesterday, $endOfDayYesterday, $station);

        return response()->json([
            'today_revenue' => $todayRevenue,
            'yesterday_revenue' => $yesterdayRevenue
        ]);
    }

    public function thisWeekRevenue(
        Request $request,
        TransactionStatisticsService $transactionStatisticsService): JsonResponse
    {
        $stationId = $request->station_id;
        $station = null;
        if ($stationId) {
            $station = MeterStation::find($stationId);
        }

        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $startOfWeekLastWeek = now()->subWeek()->startOfWeek();
        $endOfWeekLastWeek = now()->subWeek()->endOfWeek();

        $thisWeekRevenue = $transactionStatisticsService->calculateRevenue($startOfWeek, $endOfWeek, $station);
        $lastWeekRevenue = $transactionStatisticsService->calculateRevenue($startOfWeekLastWeek, $endOfWeekLastWeek, $station);

        return response()->json([
            'this_week_revenue' => $thisWeekRevenue,
            'last_week_revenue' => $lastWeekRevenue
        ]);
    }

    public function thisMonthRevenue(
        Request $request,
        TransactionStatisticsService $transactionStatisticsService): JsonResponse
    {
        $stationId = $request->station_id;
        $station = null;
        if ($stationId) {
            $station = MeterStation::find($stationId);
        }

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $startOfMonthLastMonth = now()->subMonth()->startOfMonth();
        $endOfMonthLastMonth = now()->subMonth()->endOfMonth();

        $thisMonthRevenue = $transactionStatisticsService->calculateRevenue($startOfMonth, $endOfMonth, $station);
        $lastMonthRevenue = $transactionStatisticsService->calculateRevenue($startOfMonthLastMonth, $endOfMonthLastMonth, $station);

        return response()->json([
            'this_month_revenue' => $thisMonthRevenue,
            'last_month_revenue' => $lastMonthRevenue
        ]);
    }

    public function thisYearRevenue(
        Request $request,
        TransactionStatisticsService $transactionStatisticsService): JsonResponse
    {
        $stationId = $request->station_id;
        $station = null;
        if ($stationId) {
            $station = MeterStation::find($stationId);
        }

        $startOfYear = now()->startOfYear();
        $endOfYear = now()->endOfYear();

        $startOfYearLastYear = now()->subYear()->startOfYear();
        $endOfYearLastYear = now()->subYear()->endOfYear();

        $thisYearRevenue = $transactionStatisticsService->calculateRevenue($startOfYear, $endOfYear, $station);
        $lastYearRevenue = $transactionStatisticsService->calculateRevenue($startOfYearLastYear, $endOfYearLastYear, $station);

        return response()->json([
            'this_year_revenue' => $thisYearRevenue,
            'last_year_revenue' => $lastYearRevenue
        ]);
    }

    public function monthlyRevenueStatistics(
        Request $request,
        TransactionStatisticsService $transactionStatisticsService): JsonResponse
    {
        $stationId = $request->station_id;

        $sortedRevenueSum = $transactionStatisticsService->getMonthlyRevenueStatistics($stationId);
        return response()->json($sortedRevenueSum);

    }

    public function monthlyRevenueStatisticsPerStation(
        Request $request,
        TransactionStatisticsService $transactionStatisticsService): JsonResponse
    {
        $stations = MeterStation::select('id', 'name')->get();
        $sortedRevenueSum = $transactionStatisticsService->getMonthlyRevenueStatisticsPerStation($stations);
        return response()->json($sortedRevenueSum);

    }

}
