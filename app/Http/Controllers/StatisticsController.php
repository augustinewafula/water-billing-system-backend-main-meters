<?php

namespace App\Http\Controllers;

use App\Models\DailyMeterReading;
use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterReading;
use App\Models\MeterStation;
use App\Models\MeterToken;
use App\Models\MonthlyServiceChargePayment;
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
            'revenue' => $this->calculateRevenue(null, null)
        ]);
    }

    public function previousMonthRevenueStatistics(): JsonResponse
    {
        $firstDayOfPreviousMonth = Carbon::now()->startOfMonth()->subMonthsNoOverflow()->toDateString();
        $lastDayOfPreviousMonth = Carbon::now()->subMonthsNoOverflow()->endOfMonth()->toDateString();

        $firstDayOfBeforeLastMonth = Carbon::now()->startOfMonth()->subMonthsNoOverflow(2)->toDateString();
        $lastDayOfBeforeLastMonth = Carbon::now()->subMonthsNoOverflow(2)->endOfMonth()->toDateString();

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
        $monthlyServiceChargeMonthWiseRevenue = $this->getMonthlyServiceChargeMonthWiseRevenue();
        $meterBillingMonthWiseRevenue = $this->getMeterBillingMonthWiseRevenue();
        $meterTokenMonthWiseRevenue = $this->getMeterTokenMonthWiseRevenue();

        $revenueSum = $this->calculateRevenueSum($monthlyServiceChargeMonthWiseRevenue, $meterBillingMonthWiseRevenue, $meterTokenMonthWiseRevenue);
        return response()->json($revenueSum);

    }

    public function calculateStationRevenue(?string $from, ?string $to): array
    {
        $monthlyServiceChargeSum = $this->calculateMonthlyServiceChargeSumPerStation($from, $to);
        $billingsSum = $this->calculateMeterBillingSumPerStation($from, $to);
        $tokenSum = $this->calculateMeterTokenSumPerStation($from, $to);

        return $this->calculateRevenueSum($billingsSum, $tokenSum, $monthlyServiceChargeSum);
    }

    public function calculateRevenue(?string $from, ?string $to)
    {
        $serviceChargeSum = $this->calculateMonthlyServiceChargeSum($from, $to);

        $billingsSum = $this->calculateMeterBillingsSum($from, $to);

        $tokenSum = $this->calculateMeterTokensSum($from, $to);

        return $billingsSum + $tokenSum + $serviceChargeSum;
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

    /**
     * @param string|null $from
     * @param string|null $to
     * @return mixed
     */
    private function calculateMonthlyServiceChargeSum(?string $from, ?string $to)
    {
        $serviceChargeSum = MonthlyServiceChargePayment::select('monthly_service_charge_payments.*', 'mpesa_transactions.TransAmount')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'monthly_service_charge_payments.mpesa_transaction_id');
        if ($from !== null && $to !== null) {
            $serviceChargeSum = $serviceChargeSum->where('mpesa_transactions.created_at', '>', $from)
                ->where('mpesa_transactions.created_at', '<', $to);
        }
        return $serviceChargeSum->sum('amount_paid');
    }

    /**
     * @param string|null $from
     * @param string|null $to
     * @return mixed
     */
    private function calculateMeterBillingsSum(?string $from, ?string $to)
    {
        $billingsSum = MeterBilling::select('meter_billings.*', 'mpesa_transactions.TransAmount')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id');
        if ($from !== null && $to !== null) {
            $billingsSum = $billingsSum->where('mpesa_transactions.created_at', '>', $from)
                ->where('mpesa_transactions.created_at', '<', $to);
        }
        return $billingsSum->sum('amount_paid');
    }

    /**
     * @param string|null $from
     * @param string|null $to
     * @return mixed
     */
    private function calculateMeterTokensSum(?string $from, ?string $to)
    {
        $tokenSum = MeterToken::select('meter_tokens.*', 'mpesa_transactions.TransAmount')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id');
        if ($from !== null && $to !== null) {
            $tokenSum = $tokenSum->where('mpesa_transactions.created_at', '>', $from)
                ->where('mpesa_transactions.created_at', '<', $to);
        }
        return $tokenSum->sum('TransAmount');
    }

    /**
     * @param string|null $from
     * @param string|null $to
     * @return mixed
     */
    private function calculateMonthlyServiceChargeSumPerStation(?string $from, ?string $to)
    {
        $monthlyServiceCharge = MonthlyServiceChargePayment::join('mpesa_transactions', 'mpesa_transactions.id', 'monthly_service_charge_payments.mpesa_transaction_id')
            ->join('monthly_service_charges', 'monthly_service_charges.id', 'monthly_service_charge_payments.monthly_service_charge_id')
            ->join('users', 'users.id', 'monthly_service_charges.user_id')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meter_stations.id', 'meters.station_id');
        if ($from !== null && $to !== null) {
            $monthlyServiceCharge = $monthlyServiceCharge->where('mpesa_transactions.created_at', '>', $from)
                ->where('mpesa_transactions.created_at', '<', $to);
        }
        return $monthlyServiceCharge->groupBy('name')
            ->selectRaw('sum(monthly_service_charge_payments.amount_paid) as total, meter_stations.name')
            ->get();
    }

    /**
     * @param string|null $from
     * @param string|null $to
     * @return mixed
     */
    private function calculateMeterBillingSumPerStation(?string $from, ?string $to)
    {
        $billingsSum = MeterBilling::join('meter_readings', 'meter_readings.id', 'meter_billings.meter_reading_id')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('meter_stations', 'meters.station_id', 'meter_stations.id')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id');
        if ($from !== null && $to !== null) {
            $billingsSum = $billingsSum->where('mpesa_transactions.created_at', '>', $from)
                ->where('mpesa_transactions.created_at', '<', $to);
        }
        return $billingsSum->groupBy('name')
            ->selectRaw('sum(meter_billings.amount_paid) as total, meter_stations.name')
            ->get();
    }

    /**
     * @param string|null $from
     * @param string|null $to
     * @return mixed
     */
    private function calculateMeterTokenSumPerStation(?string $from, ?string $to)
    {
        $tokenSum = MeterToken::join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('meter_stations', 'meters.station_id', 'meter_stations.id');
        if ($from !== null && $to !== null) {
            $tokenSum = $tokenSum->where('mpesa_transactions.created_at', '>', $from)
                ->where('mpesa_transactions.created_at', '<', $to);
        }
        return $tokenSum->groupBy('name')
            ->selectRaw('sum(mpesa_transactions.TransAmount) as total, meter_stations.name')
            ->get();
    }

    /**
     * @param $billingsSum
     * @param $tokenSum
     * @param $monthlyServiceCharge
     * @return mixed
     */
    private function calculateRevenueSum($billingsSum, $tokenSum, $monthlyServiceCharge)
    {
        $all = $billingsSum->concat($tokenSum)->concat($monthlyServiceCharge)->toArray();

        $all = array_reduce($all, static function ($accumulator, $item) {
            $accumulator[$item['name']] = $accumulator[$item['name']] ?? 0;
            $accumulator[$item['name']] += $item['total'];
            return $accumulator;
        });

        if ($all === null) {
            return [];
        }
        $stationsRevenue = [];
        foreach ($all as $key => $value) {
            $stationsRevenue[] = [
                'name' => $key,
                'value' => $value
            ];
        }
        return $stationsRevenue;
    }

    /**
     * @return mixed
     */
    private function getMonthlyServiceChargeMonthWiseRevenue()
    {
        return MonthlyServiceChargePayment::select('monthly_service_charge_payments.amount_paid as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'monthly_service_charge_payments.mpesa_transaction_id')
            ->whereYear('mpesa_transactions.created_at', date('Y'))
            ->distinct('name')
            ->groupBy('name', 'total')
            ->get();
    }

    /**
     * @return mixed
     */
    private function getMeterBillingMonthWiseRevenue()
    {
        return MeterBilling::select('meter_billings.amount_paid as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_billings.mpesa_transaction_id')
            ->whereYear('mpesa_transactions.created_at', date('Y'))
            ->distinct('name')
            ->groupBy('name', 'total')
            ->get();
    }

    /**
     * @return mixed
     */
    private function getMeterTokenMonthWiseRevenue()
    {
        return MeterToken::select('mpesa_transactions.TransAmount as total', DB::raw('MONTHNAME(mpesa_transactions.created_at) as name'))
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->whereYear('mpesa_transactions.created_at', date('Y'))
            ->distinct('name')
            ->groupBy('name', 'total')
            ->get();
    }
}
