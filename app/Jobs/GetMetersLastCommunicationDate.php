<?php

namespace App\Jobs;

use App\Models\Meter;
use App\Traits\GetsMeterInformation;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class GetMetersLastCommunicationDate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GetsMeterInformation;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            if ($changshaMeters = $this->getChangshaNbIotMeterReadings()) {
                foreach ($changshaMeters as $meter) {
                    $last_communication_date = Carbon::createFromFormat('Y-m-d H:i:s.u', $meter->mdate, 'Asia/Shanghai')
                        ->setTimezone('Africa/Nairobi');
                    $meter_details = (object)[
                        'meter_number' => $meter->meterno,
                        'meter_voltage' => $meter->battery,
                        'signal_intensity' => (int)$meter->rssi,
                        'last_communication_date' => $last_communication_date
                    ];
                    try {
                        $this->saveMeterLastCommunicationDate($meter_details);
                    } catch (\Throwable) {
                        continue;
                    }
                }
            }
        }  catch (\Throwable $e) {
            Log::error($e);
        }
        try {
            if ($SHMeters = $this->getShMeterReadings()) {
                foreach ($SHMeters as $meter) {
                    $last_communication_date = Carbon::createFromFormat('Y/m/d H:i:s', $meter->CommunicationTime, 'Asia/Shanghai')
                        ->setTimezone('Africa/Nairobi');
                    $meter_details = (object)[
                        'meter_number' => $meter->MeterId,
                        'meter_voltage' => $meter->MeterVoltage,
                        'signal_intensity' => (int)$meter->SignalIntensity,
                        'last_communication_date' => $last_communication_date
                    ];
                    try {
                        $this->saveMeterLastCommunicationDate($meter_details);
                    } catch (\Throwable) {
                        continue;
                    }
                }
            }
        }  catch (\Throwable $e) {
            Log::error($e);
        }
    }

    private function saveMeterLastCommunicationDate(object $meter_details): void
    {
        $database_meter = Meter::where('number', $meter_details->meter_number)->firstOrFail();
        $database_meter->update([
            'battery_voltage' => $meter_details->meter_voltage,
            'last_communication_date' => $meter_details->last_communication_date,
            'signal_intensity' => $meter_details->signal_intensity
        ]);
    }
}
