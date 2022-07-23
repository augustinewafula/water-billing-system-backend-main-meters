<?php

namespace App\Jobs;

use App\Enums\MeterMode;
use App\Enums\PaymentStatus;
use App\Enums\ValveStatus;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Traits\CalculatesUserAmount;
use App\Traits\NotifiesOnJobFailure;
use App\Traits\NotifiesUser;
use App\Traits\TogglesValveStatus;
use DB;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;
use Log;
use Throwable;

class SwitchOffUnpaidMeters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TogglesValveStatus, NotifiesOnJobFailure, CalculatesUserAmount, NotifiesUser;

    public $tries = 2;
    public $failOnTimeout = true;

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
     * @throws JsonException|Throwable
     */
    public function handle(): void
    {
        $unpaid_meters = MeterReading::with(['meter' => function ($query) {
            $query->whereValveStatus(ValveStatus::OPEN)
                ->orWhere('valve_status', null);
        }])
            ->whereDate('actual_meter_disconnection_on', '<=', now())
            ->where(function ($query) {
                $query->whereStatus(PaymentStatus::NOT_PAID)
                    ->orWhere('status', PaymentStatus::PARTIALLY_PAID);
            })
            ->get();
        foreach ($unpaid_meters as $unpaid_meter) {
            try {
                if (!$unpaid_meter->meter) {
                    continue;
                }
                $meter = Meter::with('user', 'station')->findOrFail($unpaid_meter->meter->id);
                if (!$meter->user) {
                    continue;
                }
                $paybill_number = $meter->station->paybill_number;
                $account_number = $meter->user->account_number;
                $first_name = explode(' ', trim($meter->user->name))[0];
                $total_debt = $this->calculateUserMeterReadingDebt($unpaid_meter->meter->id);
                $total_debt_formatted = number_format($total_debt);

                if ($total_debt <= 200){
                    continue;
                }
                $meter->update([
                    'valve_status' => ValveStatus::CLOSED,
                ]);
                if ($unpaid_meter->meter->mode !== MeterMode::MANUAL) {
                    Log::info("disconnecting user {$meter->user->account_number}. Total debt is {$total_debt_formatted}");
                    $this->toggleValve($meter, ValveStatus::CLOSED);
                }

                $message = "Hello $first_name, your water meter is going to be disconnected effective immediately. Please pay your total debt of Ksh $total_debt_formatted. \nPay via paybill number $paybill_number, account number $account_number";
                if ($unpaid_meter->meter->mode === MeterMode::MANUAL) {
                    $message = "Hello $first_name, you have not paid your debt of Ksh $total_debt_formatted. Your water meter is going to be disconnected effective immediately.\nPay via paybill number $paybill_number, account number $account_number";
                }
                $this->notifyUser((object)['message' => $message, 'title' => 'Water disconnection'], $meter->user, 'general');
            } catch (Throwable $th) {
                Log::error($th);
            }
        }
    }

}
