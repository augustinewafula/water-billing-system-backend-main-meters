<?php

namespace App\Traits;

use App\Http\Requests\CreateMeterReadingRequest;
use App\Models\Meter;
use App\Models\MeterCharge;
use App\Models\MeterReading;
use Carbon\Carbon;
use DB;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Log;
use Throwable;

trait StoreMeterReading
{
    /**
     * Store a newly created resource in storage.
     *
     * @param CreateMeterReadingRequest $request
     * @return Application|ResponseFactory|JsonResponse|Response
     * @throws Throwable
     */
    public function store(CreateMeterReadingRequest $request)
    {
        $meter = Meter::find($request->meter_id);
        $bill = $this->calculateBill($meter->last_reading, $request->current_reading);

        try {
            DB::transaction(static function () use ($request, $bill, $meter) {
                MeterReading::create([
                    'meter_id' => $request->meter_id,
                    'previous_reading' => $meter->last_reading,
                    'current_reading' => $request->current_reading,
                    'month' => $request->month,
                    'bill' => $bill,
                    'service_fee' => MeterCharge::take(1)->first()->service_charge
                ]);
                $meter->update([
                    'last_reading' => $request->current_reading,
                    'last_reading_date' => Carbon::now()->toDateTimeString(),
                ]);
            });
        } catch (Throwable $th) {
            Log::error($th);
            $response = ['message' => 'Something went wrong, please contact website admin for help'];
            return response($response, 422);
        }
        return response()->json($bill, 201);

    }

    public function calculateBill($previous_reading, $current_reading): float
    {
        $meter_charges = MeterCharge::where('for', 'prepay')
            ->first();
        $bill = ($current_reading - $previous_reading) * $meter_charges->cost_per_unit;
        $service_charge = $meter_charges->service_charge;
        if ($meter_charges->service_charge_in_percentage) {
            $service_charge = ($service_charge * $bill) / 100;
        }
        return round($bill + $service_charge);
    }
}
