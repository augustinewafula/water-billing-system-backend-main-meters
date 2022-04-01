<?php

namespace App\Traits;

use App\Jobs\SendSMS;
use App\Models\Meter;
use App\Models\User;
use Carbon\Carbon;

trait SendMeterReading
{
    public function sendMeterReading($meter_reading): void
    {
        $user = User::where('meter_id', $meter_reading->meter_id)
            ->first();
        if (!$user) {
            return;
        }
        $meter = Meter::with('station', 'user')
            ->find($meter_reading->meter_id);
        $user_name = ucwords($user->name);
        $due_date = Carbon::parse($meter_reading->bill_due_at)->format('d/m/Y');
        $bill_month = Carbon::parse($meter_reading->created_at)->isoFormat('MMMM YYYY');
        $units_consumed = $meter_reading->current_reading - $meter_reading->previous_reading;
        $carry_forward_balance = 0;
        if ($user->account_balance < 0) {
            $carry_forward_balance = abs($user->account_balance);
        }
        $paybill_number = $meter->station->paybill_number;
        $account_number = $meter->user->account_number;

        $message = "Hello $user_name, your water billing for $bill_month is as follows:\nReading: $meter_reading->current_reading\nPrevious reading: $meter_reading->previous_reading\nUnits consumed: $units_consumed\nBill: Ksh $meter_reading->bill\nBalance brought forward: Ksh $carry_forward_balance\nDue date: $due_date\nPay via paybill number $paybill_number, account number $account_number";
        SendSMS::dispatch($user->phone, $message, $user->id);
        $meter_reading->update([
            'sms_sent' => true,
        ]);
    }
}
