<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Requests\UpdateMeterReadingRequest;
use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterReading;
use App\Models\User;
use App\Traits\ConstructsMeterReadingMessage;
use App\Traits\GetsUserConnectionFeeBalance;
use App\Traits\SendsMeterReading;
use App\Traits\StoresMeterReading;
use Carbon\Carbon;
use DB;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Log;
use Str;
use Throwable;

class MeterReadingController extends Controller
{
    use StoresMeterReading, SendsMeterReading, GetsUserConnectionFeeBalance, ConstructsMeterReadingMessage;

    public function __construct()
    {
        $this->middleware('permission:meter-reading-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:meter-reading-create', ['only' => ['store', 'resend']]);
        $this->middleware('permission:meter-reading-edit', ['only' => ['update']]);
        $this->middleware('permission:meter-reading-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws JsonException
     */
    public function index(Request $request): JsonResponse
    {
        $meter_readings = MeterReading::select('meter_readings.id', 'meter_readings.previous_reading', 'meter_readings.current_reading', 'meter_readings.month', 'meter_readings.bill', 'meter_readings.status', 'meter_readings.bill_due_at', 'meters.id as meter_id', 'meters.number as meter_number', 'users.id as user_id', 'users.name as user_name', 'users.account_number', 'meter_readings.created_at')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('users', 'users.meter_id', 'meters.id');
        $meter_readings = $this->filterQuery($request, $meter_readings);

        $perPage = 10;
        if ($request->has('perPage')){
            $perPage = $request->perPage;
        }
        return response()->json($meter_readings->paginate($perPage));
    }


    /**
     * Display the specified resource.
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $meter_reading = MeterReading::with('meter.type', 'user.unaccounted_debts', 'meter_billings')
            ->where('id', $id)
            ->firstOrFail();
        if ($meter_reading->user->should_pay_connection_fee){
            $meter_reading->user->connection_fee_balance = $this->getUserConnectionFeeBalance($meter_reading->meter->station_id, $meter_reading->user->total_connection_fee_paid);
        }
        return response()->json($meter_reading);
    }

    public function previewMeterReadingMessage(MeterReading $meterReading): JsonResponse
    {
        $user = User::where('meter_id', $meterReading->meter_id)
            ->firstOrFail();
        return response()->json(['message' => $this->constructMeterReadingMessage($meterReading, $user)]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateMeterReadingRequest $request
     * @param MeterReading $meterReading
     * @return Application|ResponseFactory|JsonResponse|Response
     */
    public function update(UpdateMeterReadingRequest $request, MeterReading $meterReading)
    {
        if ($request->current_reading === $meterReading->current_reading) {
            $meterReading->update($request->validated());
            return response()->json(['has_message_been_resent' => false]);
        }

        $meter = Meter::find($request->meter_id);
        $user = User::where('meter_id', $meter->id)->firstOrFail();
        $bill = $this->calculateBill($request->previous_reading, $request->current_reading, $user);
        $service_fee = $this->calculateServiceFee($bill, 'post-pay');

        $has_message_been_resent = false;
        try {
            DB::beginTransaction();
            $this->removeReadingsBillFromUserAccount($meterReading);
            $meterReading->update([
                'meter_id' => $request->meter_id,
                'current_reading' => $request->current_reading,
                'month' => $request->month,
                'service_fee' => $service_fee,
                'bill' => $bill + $service_fee
            ]);
            $this->addReadingBillToUserAccount($meterReading);
            if ($meterReading->bill_due_at <= now()) {
                $meterReading->update([
                    'sms_sent' => false,
                    'send_sms_at' => now()
                ]);
                $has_message_been_resent = true;
            }
            $meter->update([
                'last_reading' => $request->current_reading,
            ]);
            DB::commit();
        } catch (Throwable $th) {
            Log::error($th);
            $response = ['message' => 'Something went wrong, please contact website admin for help'];
            return response($response, 422);
        }
        return response()->json(['has_message_been_resent' => $has_message_been_resent]);
    }

    public function resend(MeterReading $meterReading): JsonResponse
    {
        $this->sendMeterReading($meterReading);
        return response()->json('ok');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param MeterReading $meterReading
     * @return JsonResponse
     * @throws Throwable
     */
    public function destroy(MeterReading $meterReading): JsonResponse
    {
        $meter = Meter::where('id', $meterReading->meter_id)->first();
        if (!$meter) {
            $response = ['message' => 'Something went wrong'];
            return response()->json($response, 422);
        }
        $last_meter_reading = MeterReading::where('meter_id', $meter->id)
            ->latest()
            ->first();
        try {
            DB::beginTransaction();
            if ($last_meter_reading->id === $meterReading->id) {
                $meter->update([
                    'last_reading' => $last_meter_reading->previous_reading
                ]);
            }
            $this->removeReadingsBillFromUserAccount($meterReading);
            $meterReading->forceDelete();
            DB::commit();
            return response()->json('deleted');
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error($throwable);
            $response = ['message' => 'Failed to delete'];
            return response()->json($response, 422);
        }
    }

    public function addReadingBillToUserAccount($meterReading): void
    {
        $user = User::where('meter_id', $meterReading->meter_id)->firstOrFail();
        $user_total_amount = $user->account_balance - $meterReading->bill;
        $user->update(['account_balance' => $user_total_amount]);
    }

    public function removeReadingsBillFromUserAccount($meterReading): void
    {
        $user = User::where('meter_id', $meterReading->meter_id)->firstOrFail();
        if ($meterReading->status === PaymentStatus::NOT_PAID){
            $user_total_amount = $user->account_balance + $meterReading->bill;
            $user->update(['account_balance' => $user_total_amount]);
            return;
        }

        if ($meterReading->status === PaymentStatus::PARTIALLY_PAID || $meterReading->status === PaymentStatus::OVER_PAID || $meterReading->status === PaymentStatus::PAID){
            $meter_reading_payments = MeterBilling::where('meter_reading_id', $meterReading->id)->get();
            $user_total_amount = $user->account_balance;
            foreach ($meter_reading_payments as $meter_reading_payment){
                if ($meter_reading_payment->amount_paid > 0){
                    $actual_meter_reading_amount_paid = $meter_reading_payment->amount_paid - ($meter_reading_payment->monthly_service_charge_deducted + $meter_reading_payment->connection_fee_deducted);
                }else{
                    $actual_meter_reading_amount_paid = $meter_reading_payment->credit - ($meter_reading_payment->monthly_service_charge_deducted + $meter_reading_payment->connection_fee_deducted);
                }
                if ($meterReading->status === PaymentStatus::PARTIALLY_PAID || $meterReading->status === PaymentStatus::PAID){
                    $user_total_amount += $actual_meter_reading_amount_paid;
                    continue;
                }
                if ($meterReading->status === PaymentStatus::OVER_PAID){
                    $user_total_amount += ($actual_meter_reading_amount_paid - $meter_reading_payment->amount_over_paid);
                }
            }
            $user->update(['account_balance' => $user_total_amount]);
        }

    }

    /**
     * @param Request $request
     * @param $meter_readings
     * @return mixed
     * @throws JsonException
     */
    public function filterQuery(Request $request, $meter_readings)
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');
        $fromDate = $request->query('fromDate');
        $month = $request->query('month');
        $toDate = $request->query('toDate');
        $status = $request->query('status');

        if ($request->has('search') && Str::length($request->query('search')) > 0) {
            $meter_readings = $meter_readings->where(function ($meter_readings) use ($search) {
                $meter_readings->where('meter_readings.current_reading', 'like', '%' . $search . '%')
                    ->orWhere('meters.number', 'like', '%' . $search . '%')
                    ->orWhere('users.name', 'like', '%' . $search . '%')
                    ->orWhere('users.account_number', 'like', '%' . $search . '%')
                    ->orWhere('meter_readings.bill', 'like', '%' . $search . '%')
                    ->orWhere('meter_readings.previous_reading', 'like', '%' . $search . '%');
            });
        }
        if (($request->has('fromDate') && Str::length($request->query('fromDate')) > 0) && ($request->has('toDate') && Str::length($request->query('toDate')) > 0)) {
            $formattedFromDate = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
            $formattedToDate = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
            $meter_readings = $meter_readings->whereBetween('meter_readings.created_at', [$formattedFromDate, $formattedToDate]);
        }
        if ($request->has('year') && !empty($request->query('year')) && $request->query('year') !== 'undefined') {
            $meter_readings = $meter_readings->whereYear('month', $request->query('year'));

        }
        if ($request->has('month') && !empty($request->query('month')) && $request->query('month') !== 'undefined') {
            $formattedFromDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
            $meter_readings = $meter_readings->where('month', $formattedFromDate);

        }
        if ($request->has('station_id')) {
            $meter_readings = $meter_readings->join('meter_stations', 'meter_stations.id', 'meters.station_id')
                ->where('meter_stations.id', $stationId);
        }
        if ($request->has('meter_id')) {
            $meter_readings = $meter_readings->where('meters.id', $request->query('meter_id'));
        }
        if ($request->has('user_id')) {
            $meter_readings = $meter_readings->where('users.id', $request->query('user_id'));
        }
        if ($request->has('sortBy')) {
            $meter_readings = $meter_readings->orderBy($sortBy, $sortOrder);
        }
        if ($request->has('status')) {
            $decoded_status = json_decode($status, false, 512, JSON_THROW_ON_ERROR);
            if (!empty($decoded_status)){
                $meter_readings = $meter_readings->whereIn('status', $decoded_status);
            }

        }
        return $meter_readings;
    }
}
