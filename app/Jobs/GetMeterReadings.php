<?php

namespace App\Jobs;

use App\Http\Requests\CreateDailyMeterReadingRequest;
use App\Http\Requests\CreateMeterReadingRequest;
use App\Models\Meter;
use App\Traits\GetMetersInformation;
use App\Traits\StoreMeterReading;
use App\Traits\StoresDailyMeterReading;
use Carbon\Carbon;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;
use Log;
use Throwable;

class GetMeterReadings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StoreMeterReading, StoresDailyMeterReading, GetMetersInformation;

    protected $type;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws JsonException
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($changshaMeters = $this->getChangshaNbIotMeterReadings()) {
            foreach ($changshaMeters as $meter) {
                $meter_number = $meter->meterno;
                $meter_reading = $meter->lasttotalall;
                $meter_voltage = $meter->battery;
                $this->saveMeterReading($meter_number, $meter_reading, $meter_voltage);
            }
        }
        if ($SHMeters = $this->getShMeterReadings()) {
            foreach ($SHMeters as $meter) {
                $meter_number = $meter->MeterId;
                $meter_reading = $meter->PositiveCumulativeFlow;
                $meter_voltage = $meter->MeterVoltage;
                $this->saveMeterReading($meter_number, $meter_reading, $meter_voltage);
            }
        }
    }

    public function saveDailyMeterReadings($database_meter, $meter_reading, $meter_voltage): void
    {
        $request = new CreateDailyMeterReadingRequest();
        $request->setMethod('POST');
        $request->request->add([
            'meter_id' => $database_meter->id,
            'reading' => round($meter_reading)
        ]);
        try {
            DB::transaction(function () use ($request, $database_meter, $meter_voltage) {
                $this->storeDailyReading($request);
                $database_meter->update([
                    'voltage' => $meter_voltage
                ]);
            });
        } catch (Throwable $exception) {
            Log::error($exception);
        }
    }

    /**
     * @param $database_meter
     * @param $meter_reading
     * @return void
     */
    public function saveMonthlyMeterReadings($database_meter, $meter_reading): void
    {
        $request = new CreateMeterReadingRequest();
        $request->setMethod('POST');
        $request->request->add([
            'meter_id' => $database_meter->id,
            'current_reading' => round($meter_reading),
            'month' => Carbon::now()->format('Y-m')
        ]);
        try {
            DB::transaction(function () use ($request, $database_meter) {
                $this->store($request);
                $database_meter->update([
                    'last_reading_date' => Carbon::now()->toDateTimeString()
                ]);
            });
        } catch (Throwable $exception) {
            Log::error($exception);
        }
    }

    /**
     * @param $meter_number
     * @param $meter_reading
     * @param $meter_voltage
     * @return void
     */
    public function saveMeterReading($meter_number, $meter_reading, $meter_voltage): void
    {
        Log::info('Checking Meter number: ' . $meter_number);
        $database_meter = Meter::where('number', $meter_number)->first();
        if ($database_meter) {
            if ($this->type === 'daily') {
                $this->saveDailyMeterReadings($database_meter, $meter_reading, $meter_voltage);
            } else {
                $this->saveMonthlyMeterReadings($database_meter, $meter_reading);
            }
        }
    }
}
