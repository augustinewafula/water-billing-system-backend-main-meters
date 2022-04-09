<?php

namespace App\Traits;

use App\Models\MonthlyServiceCharge;
use Carbon\Carbon;

trait ProcessMonthlyServiceChargeTransaction
{
    public function hasMonthlyServiceChargeDebt($user): bool
    {
        $last_monthly_service_charge = MonthlyServiceCharge::where('user_id', $user->id)
            ->latest()
            ->limit(1)
            ->first();
        $firstDayOfPreviousMonth = Carbon::now()->startOfMonth()->subMonthsNoOverflow();

        if ($last_monthly_service_charge) {
            $last_monthly_service_charge_month = Carbon::createFromFormat('Y-m-d H:i:s', $last_monthly_service_charge->month);
            if ($last_monthly_service_charge_month->greaterThanOrEqualTo($firstDayOfPreviousMonth)) {
                return true;
            }
            return false;

        }

        $user_first_monthly_service_fee_on = Carbon::createFromFormat('Y-m-d H:i:s', $user->first_monthly_service_fee_on);
        if ($user_first_monthly_service_fee_on->greaterThanOrEqualTo($firstDayOfPreviousMonth)) {
            return true;
        }
        return false;

    }

}
