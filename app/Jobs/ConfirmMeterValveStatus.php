<?php

namespace App\Jobs;

use App\Models\Meter;
use App\Traits\GetsMeterInformation;
use App\Traits\TogglesValveStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;
use Log;
use Throwable;

class ConfirmMeterValveStatus implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GetsMeterInformation, TogglesValveStatus;

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
            Log::info('changsha...');
            if ($changshaMeters = $this->getChangshaNbIotMeterReadings()) {
                foreach ($changshaMeters as $meter) {
                    $meter_number = $meter->meterno;
                    $valve_status = $meter->valvst;
                    $this->checkValveStatus($meter_number, $valve_status);
                }
            }
        } catch (JsonException|Throwable $e) {
            Log::error($e);
        }
        try {
            Log::info('SH...');
            if ($SHMeters = $this->getShMeterReadings()) {
                foreach ($SHMeters as $meter) {
                    $meter_number = $meter->MeterId;
                    $valve_status = $meter->ValveStatus;
                    $this->checkValveStatus($meter_number, $valve_status);
                }
            }
        } catch (JsonException|Throwable $e) {
            Log::error($e);
        }
    }

    /**
     * @throws JsonException
     */
    public function checkValveStatus($meter_number, $valve_status): bool
    {
        $database_meter = Meter::where('number', $meter_number)->first();
        if (!$database_meter) {
            return false;
        }
        if ((int)$database_meter->valve_status !== (int)$valve_status) {
            Log::info("Toggling meter $database_meter->number from $valve_status to $database_meter->valve_status");
            $this->toggleValve($database_meter, $database_meter->valve_status);
        }
        return true;
    }
}
