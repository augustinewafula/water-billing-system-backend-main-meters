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

    public int $tries = 2;
    public bool $failOnTimeout = true;

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
        $unpaid_meter_readings = MeterReading::with(['meter' => function ($query) {
            $query->whereValveStatus(ValveStatus::OPEN)
                ->orWhere('valve_status', null);
            }])
            ->whereDate('actual_meter_disconnection_on', '<=', now())
            ->where(function ($query) {
                $query->whereStatus(PaymentStatus::NOT_PAID)
                    ->orWhere('status', PaymentStatus::PARTIALLY_PAID);
            })
            ->get();
        foreach ($unpaid_meter_readings as $unpaid_meter_reading) {
            try {
                if (!$unpaid_meter_reading->meter) {
                    continue;
                }
                $meter = Meter::with('user', 'station')->findOrFail($unpaid_meter_reading->meter->id);
                if (!$meter->user) {
                    continue;
                }
                $paybill_number = $meter->station->paybill_number;
                $account_number = $meter->user->account_number;
                $first_name = explode(' ', trim($meter->user->name))[0];
                $meter_reading_debt = $this->calculateUserMeterReadingDebt($unpaid_meter_reading->meter->id);
                $connection_fee_debt = $this->calculateUserConnectionFeeDebt($unpaid_meter_reading->meter->user->id);
                $total_debt = $meter_reading_debt + $connection_fee_debt;

                $meter_reading_debt_formatted = number_format($meter_reading_debt);
                $connection_fee_debt_formatted = number_format($connection_fee_debt);
                $total_debt_formatted = number_format($total_debt);

                if ($total_debt <= 200){
                    continue;
                }
                Log::info('Auto switching off unpaid meter id: '. $unpaid_meter_reading->meter->id);

                $debt_breakdown = '';
                if ($connection_fee_debt > 0) {
                    $debt_breakdown .= "\nMeter Billing Debt: Ksh {$meter_reading_debt_formatted} \nConnection Fee Debt: Ksh $connection_fee_debt_formatted.";
                }

                $supplementary_message = 'your water meter is going to be disconnected effective immediately. ';

                if ($unpaid_meter_reading->disconnection_sms_sent) {
                    $supplementary_message = 'please pay the remaining pending debt to be reconnected. ';
                }

                $message = "Hello $first_name, $supplementary_message $debt_breakdown \nTotal outstanding Debt: Ksh $total_debt_formatted \nPay via paybill number $paybill_number, account number $account_number";

                $meter->update([
                    'valve_status' => ValveStatus::CLOSED,
                ]);
                if ($unpaid_meter_reading->meter->mode !== MeterMode::MANUAL) {
                    Log::info("disconnecting user {$meter->user->account_number}. Total debt is {$total_debt_formatted}");
                    $this->toggleValve($meter, ValveStatus::CLOSED);
                }
                $this->notifyUser((object)['message' => $message, 'title' => 'Water disconnection'], $meter->user, 'general');

                $meter_reading = MeterReading::findOrFail($unpaid_meter_reading->id);
                $meter_reading->update([
                    'disconnection_sms_sent' => true,
                ]);
            } catch (Throwable $th) {
                Log::error($th);
            }
        }
    }

}
