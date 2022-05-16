<?php

namespace App\Jobs;

use App\Enums\FaultyMeterFaultType;
use App\Models\FaultyMeter;
use App\Models\Meter;
use App\Traits\GeneratesPassword;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\Rule;
use Validator;

class CheckFaultyMeter implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeneratesPassword;
    protected $maximum_meter_communication_delay_time;
    protected $minimum_battery_voltage;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $thirty_hours_ago = Carbon::now()->subDay()->subHours(6);
        $this->maximum_meter_communication_delay_time = $thirty_hours_ago;
        $this->minimum_battery_voltage = 0.5;
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 600;

    /**
     * The unique ID of the job.
     *
     * @return string
     * @throws Exception
     */
    public function uniqueId(): string
    {
        return $this->generatePassword(5);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->checkMetersWithDelayedCommunication();
        $this->checkMetersWithLowBattery();
        $this->removedFixedMeters();
    }

    private function checkMetersWithDelayedCommunication(): void
    {
        $meters_with_delayed_communication = Meter::with('station')
            ->where('last_communication_date', '<', $this->maximum_meter_communication_delay_time)
            ->get();

        $this->saveAndNotify($meters_with_delayed_communication, FaultyMeterFaultType::LostCommunication);
    }

    private function checkMetersWithLowBattery(): void
    {
        $meter_with_low_battery = Meter::with('station')
            ->where('battery_voltage', '<', $this->minimum_battery_voltage)
            ->get();

        $this->saveAndNotify($meter_with_low_battery, FaultyMeterFaultType::LowBattery);
    }

    /**
     * @param $faulty_meters
     * @param $fault_type
     * @return void
     */
    private function saveAndNotify($faulty_meters, $fault_type): void
    {
        foreach ($faulty_meters as $meter) {
            $meter_id = $meter->id;
            $validator = Validator::make(['meter_id' => $meter_id], [
                'meter_id' => Rule::unique('faulty_meters')->where(function ($query) use ($meter_id, $fault_type) {
                    return $query->where('meter_id', $meter_id)
                        ->where('fault_type', $fault_type);
                })
            ]);
            if ($validator->fails()) {
                continue;
            }
            FaultyMeter::create([
                'meter_id' => $meter_id,
                'fault_type' => $fault_type
            ]);
            $station_name = $meter->station->name;
            $message = "Meter number $meter->number belonging to $station_name has ";
            if ($fault_type === FaultyMeterFaultType::LowBattery){
                $message .= "battery voltage of below $this->minimum_battery_voltage volts.";
            }
            $difference_in_hours = $this->maximum_meter_communication_delay_time->diffInHours(now());
            if ($fault_type === FaultyMeterFaultType::LostCommunication){
                $message .= "not communicated with the server for more than $difference_in_hours hours";
            }
            SendAlert::dispatch($message);
        }
    }

    private function removedFixedMeters(): void
    {
        $faulty_meters = FaultyMeter::with('meter')->get();
        foreach ($faulty_meters as $faulty_meter){
            if ($faulty_meter->fault_type === FaultyMeterFaultType::LostCommunication && $this->isDelayedCommunicationFixed($faulty_meter)){
                FaultyMeter::find($faulty_meter->id)->forceDelete();
            }
            if ($faulty_meter->fault_type === FaultyMeterFaultType::LowBattery && $this->isLowBatteryFixed($faulty_meter)){
                FaultyMeter::find($faulty_meter->id)->forceDelete();
            }
        }
    }

    private function isLowBatteryFixed($faulty_meter): bool
    {
        return $faulty_meter->meter->battery_voltage > $this->minimum_battery_voltage;
    }

    private function isDelayedCommunicationFixed($faulty_meter): bool
    {
        return $faulty_meter->meter->last_communication_date > $this->maximum_meter_communication_delay_time;

    }
}
