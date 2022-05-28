<?php

namespace App\Traits;

use App\Models\User;
use Carbon\Carbon;

trait SendsMeterReading
{
    use NotifiesUser, ConstructsMeterReadingMessage;

    public function sendMeterReading($meter_reading): void
    {
        $user = User::where('meter_id', $meter_reading->meter_id)
            ->firstOrFail();

        $bill_month = Carbon::parse($meter_reading->month)->isoFormat('MMMM YYYY');
        $message = $this->constructMeterReadingMessage($meter_reading, $user);

        $this->notifyUser((object)['message' => $message, 'bill_month' =>$bill_month], $user, 'meter readings');
        $meter_reading->update([
            'sms_sent' => true,
        ]);
    }


}
