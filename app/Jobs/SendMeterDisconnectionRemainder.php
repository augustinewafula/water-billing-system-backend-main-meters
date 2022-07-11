<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Enums\ValveStatus;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Traits\CalculatesUserAmount;
use App\Traits\NotifiesUser;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Throwable;

class SendMeterDisconnectionRemainder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CalculatesUserAmount, NotifiesUser;

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
     */
    public function handle()
    {
        $unpaid_meters = MeterReading::with('meter')
            ->where('disconnection_remainder_sms_sent', false)
            ->whereDate('bill_due_at', '<=', now())
            ->where(function ($query) {
                $query->whereStatus(PaymentStatus::NotPaid)
                    ->orWhere('status', PaymentStatus::Balance);
            })
            ->take(15)
            ->get();
        foreach ($unpaid_meters as $unpaid_meter) {
            try {
                if (!$unpaid_meter->meter) {
                    continue;
                }
                if ($unpaid_meter->meter->valve_status === ValveStatus::Closed) {
                    continue;
                }
                $meter = Meter::with('user', 'station')->findOrFail($unpaid_meter->meter->id);
                if (!$meter->user) {
                    continue;
                }
                $paybill_number = $meter->station->paybill_number;
                $account_number = $meter->user->account_number;
                $first_name = explode(' ', trim($meter->user->name))[0];
                $total_debt_formatted = number_format($this->calculateUserMeterReadingDebt($unpaid_meter->meter->id));
                $tell_user_meter_disconnection_on = Carbon::createFromFormat('Y-m-d H:i:s', $unpaid_meter->tell_user_meter_disconnection_on)->toFormattedDateString();

                $message = "Hello $first_name, your water bill is passed due date. Your meter shall be disconnected by $tell_user_meter_disconnection_on. Please pay your total debt of Ksh $total_debt_formatted to avoid disconnection.\nPay via paybill number $paybill_number, account number $account_number";

                $this->notifyUser((object)['message' => $message, 'title' => 'Water bill debt'], $meter->user, 'general');
                $meter_reading = MeterReading::find($unpaid_meter->id);
                $meter_reading->update(['disconnection_remainder_sms_sent' => true]);
            } catch (Throwable $th) {
                Log::error($th);
            }
        }
    }
}
