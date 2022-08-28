<?php

namespace App\Traits;

use App\Models\Meter;
use App\Models\MeterBilling;
use Carbon\Carbon;

trait ConstructsMeterReadingMessage
{
    /**
     * @param $meter_reading
     * @param $user
     * @return string
     */
    private function constructMeterReadingMessage($meter_reading, $user): string
    {
        $meter = Meter::with('station', 'user')
            ->find($meter_reading->meter_id);
        $user_name = $user->name;
        $due_date = Carbon::parse($meter_reading->bill_due_at)->format('d/m/Y');
        $bill_month = Carbon::parse($meter_reading->month)->isoFormat('MMMM YYYY');
        $units_consumed = $meter_reading->current_reading - $meter_reading->previous_reading;
        $bill = round($meter_reading->bill - $meter_reading->service_fee);
        $carry_forward_balance = 0;
        $credit = 0;

        $user_total_debt = $user->unaccounted_debt;
        if ($user->account_balance < 0) {
            $user_total_debt += abs($user->account_balance);
            $remaining_amount = abs($user->account_balance) - $meter_reading->bill;
            if ($remaining_amount < 0) {
                $credit = abs($remaining_amount);
            } else {
                $carry_forward_balance = $remaining_amount;
            }
        }
        if ($meter_billing = MeterBilling::where('meter_reading_id', $meter_reading->id)->first()) {
            $credit = $meter_billing->credit + $meter_reading->amount_paid;
        }
//        $user_total_debt -= $carry_forward_balance;
        $user_total_debt_formatted = number_format($user_total_debt);
        $carry_forward_balance += $user->unaccounted_debt;
        $carry_forward_balance_formatted = number_format($carry_forward_balance);
        $credit_formatted = number_format($credit);

        $paybill_number = $meter->station->paybill_number;
        $account_number = $meter->user->account_number;
        $service_fee = round($meter_reading->service_fee);
        $service_fee_formatted = number_format($service_fee);
        $user_account_balance = max($user->account_balance, 0);
        $user_account_balance_formatted = number_format($user_account_balance);
        $user_account_balance_text = $user_account_balance > 0 ? "\nCredit balance: Ksh $user_account_balance_formatted" : '';

        return "Hello $user_name, your water billing for $bill_month is as follows:\nCurrent reading: $meter_reading->current_reading\nPrevious reading: $meter_reading->previous_reading\nUnits consumed: $units_consumed\nBalance brought forward: Ksh $carry_forward_balance_formatted\nCredit applied: Ksh $credit_formatted\nStanding charge: Ksh $service_fee_formatted\nTotal outstanding: Ksh $user_total_debt_formatted $user_account_balance_text\nDue date: $due_date\nPay via paybill number $paybill_number, account number $account_number";
    }

}
