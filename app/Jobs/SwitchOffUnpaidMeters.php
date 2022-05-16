<?php

namespace App\Jobs;

use App\Enums\MeterMode;
use App\Enums\PaymentStatus;
use App\Enums\ValveStatus;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Traits\CalculatesUserAmount;
use App\Traits\GeneratesPassword;
use App\Traits\NotifiesOnJobFailure;
use App\Traits\TogglesValveStatus;
use DB;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;
use Log;
use Throwable;

class SwitchOffUnpaidMeters implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TogglesValveStatus, NotifiesOnJobFailure, CalculatesUserAmount, GeneratesPassword;

    public $tries = 2;

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
     * @throws JsonException|Throwable
     */
    public function handle(): void
    {
        $unpaid_meters = MeterReading::with(['meter' => function ($query) {
            $query->whereValveStatus(ValveStatus::Open)
                ->orWhere('valve_status', null);
        }])
            ->whereDate('actual_meter_disconnection_on', '<=', now())
            ->where(function ($query) {
                $query->whereStatus(PaymentStatus::NotPaid)
                    ->orWhere('status', PaymentStatus::Balance);
            })
            ->get();
        $processed_meters = [];
        foreach ($unpaid_meters as $unpaid_meter) {
            try {
                if (in_array($unpaid_meter->meter->id, $processed_meters, true)) {
                    continue;
                }
                if (!$unpaid_meter->meter) {
                    continue;
                }
                $meter = Meter::with('user', 'station')->findOrFail($unpaid_meter->meter->id);
                DB::beginTransaction();
                if ($unpaid_meter->meter->mode !== MeterMode::Manual) {
                    $this->toggleValve($meter, ValveStatus::Closed);
                }
                $meter->update([
                    'valve_status' => ValveStatus::Closed,
                ]);
                DB::commit();
                if (!$meter->user) {
                    continue;
                }
                $paybill_number = $meter->station->paybill_number;
                $account_number = $meter->user->account_number;
                $first_name = explode(' ', trim($meter->user->name))[0];
                $total_debt = $this->calculateUserMeterReadingDebt($unpaid_meter->meter->id);

                $message = "Hello $first_name, your water meter has been switched off. Please pay your total debt of Ksh $total_debt. \nPay via paybill number $paybill_number, account number $account_number";
                if ($unpaid_meter->meter->mode === MeterMode::Manual) {
                    $message = "Hello $first_name, you have not paid your debt of Ksh $total_debt. Your water meter is going to be disconnected effective immediately.\nPay via paybill number $paybill_number, account number $account_number";
                }
                SendSMS::dispatch($meter->user->phone, $message, $meter->user->id);
                $processed_meters[] = $unpaid_meter->meter->id;
            } catch (Throwable $th) {
                DB::rollBack();
                Log::error($th);
            }
        }
    }

}
