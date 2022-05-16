<?php

namespace App\Jobs;

use App\Enums\MeterMode;
use App\Enums\PaymentStatus;
use App\Enums\ValveStatus;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Traits\CalculatesUserAmount;
use App\Traits\GeneratesPassword;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Throwable;

class SendMeterDisconnectionRemainder implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CalculatesUserAmount, GeneratesPassword;

    public $tries = 3;

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
     */
    public function handle()
    {
        $unpaid_meters = MeterReading::with(['meter' => function ($query) {
            $query->whereValveStatus(ValveStatus::Open)
                ->orWhere('valve_status', null);
        }])
            ->where('disconnection_remainder_sms_sent', false)
            ->whereDate('tell_user_meter_disconnection_on', '<=', now())
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
                if (!$meter->user) {
                    continue;
                }
                $paybill_number = $meter->station->paybill_number;
                $account_number = $meter->user->account_number;
                $first_name = explode(' ', trim($meter->user->name))[0];
                $total_debt = $this->calculateUserMeterReadingDebt($unpaid_meter->meter->id);
                $tell_user_meter_disconnection_on = Carbon::createFromFormat('Y-m-d H:i:s', $unpaid_meter->tell_user_meter_disconnection_on)->toFormattedDateString();

                $message = "Hello $first_name, your water bill is passed due date. Your meter shall be disconnected by $tell_user_meter_disconnection_on. Please pay your total debt of Ksh $total_debt to avoid disconnection.\nPay via paybill number $paybill_number, account number $account_number";

                SendSMS::dispatch($meter->user->phone, $message, $meter->user->id);
                $processed_meters[] = $unpaid_meter->meter->id;
                $meter_reading = MeterReading::find($unpaid_meter->id);
                $meter_reading->update(['disconnection_remainder_sms_sent' => true]);
            } catch (Throwable $th) {
                Log::error($th);
            }
        }
    }
}
