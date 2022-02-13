<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateMeterReadingRequest;
use App\Models\Meter;
use App\Models\MeterCharge;
use App\Models\MeterReading;
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
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateMeterReadingRequest $request
     * @return Application|ResponseFactory|JsonResponse|Response
     * @throws Throwable
     */
    public function store(CreateMeterReadingRequest $request)
    {
        $previous_reading = Meter::find($request->meter_id)
            ->first()
            ->last_reading;
        $bill = $this->calculateBill($previous_reading, $request->current_reading);

        try {
            DB::transaction(static function () use ($request, $bill, $previous_reading) {
                MeterReading::create([
                    'meter_id' => $request->meter_id,
                    'previous_reading' => $previous_reading,
                    'current_reading' => $request->current_reading,
                    'month' => $request->month,
                    'bill' => $bill
                ]);
            });
        } catch (Throwable $th) {
            Log::error($th);
            $response = ['message' => 'Something went wrong, please contact website admin for help'];
            return response($response, 422);
        }
        return response()->json($bill, 201);

    }

    public function calculateBill($previous_reading, $current_reading)
    {
        $meter_charges = MeterCharge::take(1)
            ->first();
        return (
                ($current_reading - $previous_reading) * $meter_charges->cost_per_unit)
            + $meter_charges->service_charge;
    }

    /**
     * Display the specified resource.
     *
     * @param MeterReading $meterReading
     * @return Response
     */
    public function show(MeterReading $meterReading)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param MeterReading $meterReading
     * @return Response
     */
    public function update(Request $request, MeterReading $meterReading)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param MeterReading $meterReading
     * @return Response
     */
    public function destroy(MeterReading $meterReading)
    {
        //
    }
}
