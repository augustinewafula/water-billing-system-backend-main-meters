<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMeterReadingRequest;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Traits\StoreMeterReading;
use DB;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Log;
use Throwable;

class MeterReadingController extends Controller
{
    use StoreMeterReading;
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $station_id = $request->query('station_id');
        $meter_readings = MeterReading::query();
        if ($request->has('station_id')) {
            $meter_readings->whereHas('meter', function ($query) use ($station_id) {
                $query->where('station_id', $station_id);
            });
        }
        $meter_readings->with(['meter', 'user']);
        $meter_readings = $meter_readings->get();
        return response()->json($meter_readings);
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
        $meter_reading = MeterReading::with('meter')
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
        if ($request->current_reading == $meterReading->current_reading) {
            $meterReading->update($request->validated());
            return response()->json($meterReading);
        }

        $meter = Meter::find($request->meter_id);
        $bill = $this->calculateBillUpdate($meter->last_reading, $request->current_reading, $meterReading->bill);

        try {
            DB::transaction(static function () use ($request, $bill, $meter, $meterReading) {
                $meterReading->update([
                    'meter_id' => $request->meter_id,
                    'previous_reading' => $meter->last_reading,
                    'current_reading' => $request->current_reading,
                    'month' => $request->month,
                    'bill' => $bill
                ]);
                $meter->update([
                    'last_reading' => $request->current_reading,
                ]);
            });
        } catch (Throwable $th) {
            Log::error($th);
            $response = ['message' => 'Something went wrong, please contact website admin for help'];
            return response($response, 422);
        }
        return response()->json($bill);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param MeterReading $meterReading
     * @return JsonResponse
     */
    public function destroy(MeterReading $meterReading): JsonResponse
    {
        $meterReading->delete();
        return response()->json('deleted');
    }
}
