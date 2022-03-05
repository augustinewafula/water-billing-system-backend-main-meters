<?php

namespace App\Traits;

use App\Http\Requests\CreateMeterReadingRequest;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Setting;
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
    use calculateBill;
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

        $next_month = Carbon::now()->add(1, 'month')->format('M');
        $bill_due_days = Setting::where('key', 'bill_due_days')
            ->first()
            ->value;
        $meter_reading_sms_delay_days = Setting::where('key', 'meter_reading_sms_delay_days')
            ->first()
            ->value;
        $due_date = Carbon::parse($bill_due_days . 'th ' . $next_month)->toDateTimeString();

        try {
            DB::beginTransaction();
            MeterReading::create([
                'meter_id' => $request->meter_id,
                'previous_reading' => $meter->last_reading,
                'current_reading' => $request->current_reading,
                'month' => $request->month,
                'bill' => $bill,
                'service_fee' => $this->calculateServiceFee($bill, 'post-pay'),
                'send_sms_at' => Carbon::now()->add($meter_reading_sms_delay_days, 'day')->toDateTimeString(),
                'bill_due_at' => $due_date,
            ]);
            $meter->update([
                'last_reading' => $request->current_reading,
                'last_reading_date' => Carbon::now()->toDateTimeString(),
            ]);
            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error($th);
            $response = ['message' => 'Something went wrong, please contact website admin for help'];
            return response($response, 422);
        }
        return response()->json($bill, 201);

    }

}
