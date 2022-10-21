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
            'revenue' => $this->calculateRevenue(null, null),
            'faulty_meters' => $faultyMeters
        ]);
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
            $related_table_name = $this->getTransactionRelatedTableName($mpesaTransaction->id);
            if ($related_table_name !== '') {
                $revenue = MpesaTransaction::select('TransAmount as total', DB::raw('MONTHNAME(created_at) as name'))
                    ->where('TransID', $mpesaTransaction->TransID)
                    ->whereYear('created_at', date('Y'))
                    ->distinct('name')
                    ->groupBy('name', 'total')
                    ->get();
                $monthWiseRevenue = $monthWiseRevenue->merge($revenue);
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
            $relatedTableName = $this->getTransactionRelatedTableName($mpesaTransaction->id);
            if ($relatedTableName !== ''){
                if ($meterStation) {
                    $modelName = Str::studly(Str::singular($relatedTableName));
                    $mpesaTransactionMeterStationId = $this->getMpesaTransactionMeterStationId($modelName, $mpesaTransaction->id);
                    if ($mpesaTransactionMeterStationId !== $meterStation->id) {
                        continue;
                    }
                }
                $sum += $mpesaTransaction->TransAmount;
            }
        }

        return $sum;
    }

    public function getTransactionRelatedTableName(string $transactionId): String
    {
        $meterBillingsCount = MeterBilling::where('mpesa_transaction_id', $transactionId)->count();
        if ($meterBillingsCount > 0) {
            return 'meter_billings';
        }
        $meterTokensCount = MeterToken::where('mpesa_transaction_id', $transactionId)->count();
        if ($meterTokensCount > 0) {
            return 'meter_tokens';
        }
        $connectionFeePaymentsCount = ConnectionFeePayment::where('mpesa_transaction_id', $transactionId)->count();
        if ($connectionFeePaymentsCount > 0) {
            return 'connection_fee_payments';
        }
        $creditAccountsCount = CreditAccount::where('mpesa_transaction_id', $transactionId)->count();
        if ($creditAccountsCount > 0) {
            return 'credit_accounts';
        }
        $unaccountedDebtsCount = UnaccountedDebt::where('mpesa_transaction_id', $transactionId)->count();
        if ($unaccountedDebtsCount > 0) {
            return 'unaccounted_debts';
        }
        return '';
    }

    private function getMpesaTransactionMeterStationId(string $modelName, string $transactionId): string
    {
        $className = "App\\Models\\$modelName";
        $model = $className::where('mpesa_transaction_id', $transactionId)->first();

        if ($modelName === 'ConnectionFeePayment') {
            $model->user_id = $model->with('connection_fee')
                ->first()
                ->connection_fee
                ->user_id;
        }
        if ($modelName === 'MeterBilling') {
            $model->meter_id = $model->with('meter_reading')
                ->first()
                ->meter_reading
                ->meter_id;
        }
        if ($model->user_id) {
            $model->meter_id = $className::where('mpesa_transaction_id', $transactionId)
                ->with('user')
                ->first()
                ->user
                ->meter_id;
        }
        return Meter::findOrFail($model->meter_id)
            ->station_id;
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
            $mpesaTransactions = $mpesaTransactions->whereDate('created_at', '>', $from)
                ->whereDate('created_at', '<', $to);
        }
        return $mpesaTransactions->get();
    }

}
