<?php

namespace App\Jobs;

use App\Http\Requests\CreateDailyMeterReadingRequest;
use App\Http\Requests\CreateMeterReadingRequest;
use App\Models\Meter;
use App\Traits\GetsMeterInformation;
use App\Traits\NotifiesOnJobFailure;
use App\Traits\StoresMeterReading;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StoresMeterReading, StoresDailyMeterReading, GetsMeterInformation, NotifiesOnJobFailure;

    protected $type;

    public $tries = 1;
    public $failOnTimeout = true;

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
                $last_communication_date = Carbon::createFromFormat('Y-m-d H:i:s.u', $meter->mdate, 'Asia/Shanghai')
                    ->setTimezone('Africa/Nairobi');
                $meter_details = (object)[
                    'meter_number' => $meter->meterno,
                    'meter_reading' => (int)$meter->lasttotalall,
                    'meter_voltage' => $meter->battery,
                    'signal_intensity' => (int)$meter->rssi,
                    'last_communication_date' => $last_communication_date
                ];
                $this->saveMeterReading($meter_details);
            }
        }
        if ($SHMeters = $this->getShMeterReadings()) {
            foreach ($SHMeters as $meter) {
                $last_communication_date = Carbon::createFromFormat('Y/m/d H:i:s', $meter->CommunicationTime, 'Asia/Shanghai')
                    ->setTimezone('Africa/Nairobi');
                $meter_details = (object)[
                    'meter_number' => $meter->MeterId,
                    'meter_reading' => (int)$meter->PositiveCumulativeFlow,
                    'meter_voltage' => $meter->MeterVoltage,
                    'signal_intensity' => (int)$meter->SignalIntensity,
                    'last_communication_date' => $last_communication_date
                ];
                $this->saveMeterReading($meter_details);
            }
        }
    }

    public function saveDailyMeterReadings($database_meter, $meter_details): void
    {
        $request = new CreateDailyMeterReadingRequest();
        $request->setMethod('POST');
        $request->request->add([
            'meter_id' => $database_meter->id,
            'reading' => round($meter_details->meter_reading)
        ]);
        try {
            DB::beginTransaction();
            $this->storeDailyReading($request);
            $database_meter->update([
                'battery_voltage' => $meter_details->meter_voltage,
                'last_communication_date' => $meter_details->last_communication_date,
                'signal_intensity' => $meter_details->signal_intensity
            ]);
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
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
     * @param $meter_details
     * @return void
     */
    public function saveMeterReading($meter_details): void
    {
//        Log::info('Checking Meter number: ' . $meter_details->meter_number);
        $database_meter = Meter::where('number', $meter_details->meter_number)->first();
        if ($database_meter) {
            if ($this->type === 'daily') {
                $this->saveDailyMeterReadings($database_meter, $meter_details);
            } else {
                $this->saveMonthlyMeterReadings($database_meter, $meter_details->meter_reading);
            }
        }
    }
}
