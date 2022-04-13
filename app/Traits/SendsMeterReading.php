<?php

namespace App\Traits;

use App\Jobs\SendSMS;
use App\Models\Meter;
use App\Models\User;
use Carbon\Carbon;

trait SendsMeterReading
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
        $user_name = $user->name;
        $due_date = Carbon::parse($meter_reading->bill_due_at)->format('d/m/Y');
        $bill_month = Carbon::parse($meter_reading->created_at)->isoFormat('MMMM YYYY');
        $units_consumed = $meter_reading->current_reading - $meter_reading->previous_reading;
        $bill = round($meter_reading->bill - $meter_reading->service_fee);
        $carry_forward_balance = 0;
        $over_paid_amount = 0;
        if ($user->account_balance < 0) {
            $carry_forward_balance = abs($user->account_balance);
        }
        if ($user->account_balance > 0) {
            $over_paid_amount = abs($user->account_balance);
        }
        $paybill_number = $meter->station->paybill_number;
        $account_number = $meter->user->account_number;
        $total_outstanding = round(($meter_reading->bill + $carry_forward_balance) - $over_paid_amount);
        $service_fee = round($meter_reading->service_fee);
        if ($total_outstanding < 0) {
            $total_outstanding = 0;
        }

        $message = "Dear $user_name, your water billing for $bill_month is as follows:\nCurrent reading: $meter_reading->current_reading\nPrevious reading: $meter_reading->previous_reading\nUnits consumed: $units_consumed\nBalance brought forward: Ksh $carry_forward_balance\nCredit applied: Ksh $over_paid_amount\nStanding charge: Ksh $service_fee\nTotal outstanding: Ksh $total_outstanding\nDue date: $due_date\nPay via paybill number $paybill_number, account number $account_number";
        SendSMS::dispatch($user->phone, $message, $user->id);
        $meter_reading->update([
            'sms_sent' => true,
        ]);
    }
}
