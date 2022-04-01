<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMeterReadingRequest;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Traits\SendMeterReading;
use App\Traits\StoreMeterReading;
use DB;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Log;
use Str;
use Throwable;

class MeterReadingController extends Controller
{
    use StoreMeterReading, SendMeterReading;
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $meter_readings = MeterReading::select('meter_readings.id', 'meter_readings.previous_reading', 'meter_readings.current_reading', 'meter_readings.month', 'meter_readings.bill', 'meter_readings.status', 'meter_readings.bill_due_at', 'meters.id as meter_id', 'meters.number as meter_number', 'users.id as user_id', 'users.name as user_name', 'meter_readings.created_at')
            ->join('meters', 'meters.id', 'meter_readings.meter_id')
            ->join('users', 'users.meter_id', 'meters.id');
        $meter_readings = $this->filterQuery($request, $meter_readings);
        return response()->json($meter_readings->paginate(10));
    }



    public function calculateBillUpdate($previous_reading, $current_reading, $previous_bill): float
    {
        return round((
            ($current_reading * $previous_bill) / $previous_reading));
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $meter_reading = MeterReading::with('meter.type', 'user', 'meter_billings')
            ->where('id', $id)
            ->first();
        return response()->json($meter_reading);
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
        $bill = $this->calculateBillUpdate($meter->last_reading, $request->current_reading, $meterReading->bill);

        $has_message_been_resent = false;
        try {
            DB::beginTransaction();
            $meterReading->update([
                'meter_id' => $request->meter_id,
                'current_reading' => $request->current_reading,
                'month' => $request->month,
                'bill' => $bill,
                'sms_sent' => false
            ]);
            if ($meterReading->bill_due_at <= now()) {
                $meterReading->update([
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
     */
    public function destroy(MeterReading $meterReading): JsonResponse
    {
        try {
            $meterReading->delete();
            return response()->json('deleted');
        } catch (Throwable $throwable) {
            Log::error($throwable);
            $response = ['message' => 'Failed to delete'];
            return response()->json($response, 422);
        }
    }

    /**
     * @param Request $request
     * @param $meter_readings
     * @return mixed
     */
    public function filterQuery(Request $request, $meter_readings)
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');

        if ($request->has('search') && Str::length($request->query('search')) > 0) {
            $meter_readings = $meter_readings->where(function ($meter_readings) use ($search) {
                $meter_readings->where('meter_readings.current_reading', 'like', '%' . $search . '%')
                    ->orWhere('meters.number', 'like', '%' . $search . '%')
                    ->orWhere('users.name', 'like', '%' . $search . '%')
                    ->orWhere('meter_readings.bill', 'like', '%' . $search . '%')
                    ->orWhere('meter_readings.previous_reading', 'like', '%' . $search . '%');
            });
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
        return $meter_readings;
    }
}
