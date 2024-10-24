<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Models\ConnectionFee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait SendsMeterReading
{
    use NotifiesUser, ConstructsMeterReadingMessage;

    public function sendMeterReading($meter_reading): void
    {
        $user = User::where('meter_id', $meter_reading->meter_id)
            ->first();

        if ($user === null) {
            Log::warning('User not found for meter '.$meter_reading->meter_id);
            return;
        }

        $bill_month = Carbon::parse($meter_reading->month)->isoFormat('MMMM YYYY');
        $message = $this->constructMeterReadingMessage($meter_reading, $user);
        $this->updateConnectionFeeBillReminderSmsSentStatus($user->id, $meter_reading->bill_due_at);

        $this->notifyUser((object)['message' => $message, 'bill_month' =>$bill_month], $user, 'meter readings');
        $meter_reading->update([
            'sms_sent' => true,
        ]);
    }

    private function updateConnectionFeeBillReminderSmsSentStatus(String $user_id, Carbon $remainder_date): void
    {
        $connection_fees = ConnectionFee::where('user_id', $user_id)
            ->whereDate('month', '<=', $remainder_date)
            ->where(function ($query) {
                $query->whereStatus(PaymentStatus::PARTIALLY_PAID)
                    ->orWhere('status', PaymentStatus::NOT_PAID);
            })
            ->where('bill_remainder_sms_sent', false)
            ->get();

        foreach ($connection_fees as $connection_fee) {
            $connection_fee->update([
                'bill_remainder_sms_sent' => true,
            ]);
        }
    }

}
