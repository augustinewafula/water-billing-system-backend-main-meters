<?php

namespace App\Jobs;

use App\Enums\ValveStatus;
use App\Models\MeterReading;
use App\Traits\CalculatesUserAmount;
use App\Traits\NotifiesUser;
use Carbon\Carbon;
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

    public int $tries = 2;
    public $failOnTimeout = true;

    protected MeterReading $meterReading;

    /**
     * Create a new job instance.
     *
     * @param MeterReading $meterReading
     */
    public function __construct(MeterReading $meterReading)
    {
        $this->meterReading = $meterReading;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Throwable
     */
    public function handle(): void
    {
        try {
            $meter = $this->meterReading->meter;
            if (!$meter || !$meter->user || $meter->valve_status === ValveStatus::CLOSED) {
                return;
            }

            $paybillNumber = $meter->station->paybill_number;
            $accountNumber = $meter->user->account_number;
            $firstName = explode(' ', trim($meter->user->name))[0];
            $totalDebt = number_format($this->calculateUserMeterReadingDebt($meter->id));
            $disconnectionDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->meterReading->tell_user_meter_disconnection_on)
                ->toFormattedDateString();

            $message = "Hello $firstName, your water bill is past due. Your meter shall be disconnected by $disconnectionDate. " .
                "Please pay your total debt of Ksh $totalDebt to avoid disconnection. " .
                "Pay via paybill number $paybillNumber, account number $accountNumber.";

            $this->notifyUser((object)['message' => $message, 'title' => 'Water bill debt'], $meter->user, 'general');

            $this->meterReading->update(['disconnection_remainder_sms_sent' => true]);

        } catch (Throwable $th) {
            Log::error($th);
            throw $th;
        }
    }
}
