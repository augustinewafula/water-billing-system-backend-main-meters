<?php

namespace App\Traits;

use App\Models\MonthlyServiceCharge;
use Carbon\Carbon;

trait GeneratesMonthlyServiceCharge
{
    public function generate($user, $monthly_service_charge): void
    {
        $firstDayOfCurrentMonth = Carbon::now()->startOfMonth();
        $monthToGenerate = $this->fromMonth($user);
        while ($monthToGenerate->lessThanOrEqualTo($firstDayOfCurrentMonth)) {
            MonthlyServiceCharge::create([
                'user_id' => $user->id,
                'month' => $monthToGenerate,
                'service_charge' => $monthly_service_charge
            ]);
            $monthToGenerate->add(1, 'month');
        }
    }

    public function fromMonth($user)
    {
        $last_monthly_service_charge = MonthlyServiceCharge::where('user_id', $user->id)
            ->latest('month')
            ->limit(1)
            ->first();

        if ($last_monthly_service_charge) {
            return Carbon::createFromFormat('Y-m', $last_monthly_service_charge->month)->add(1, 'month');

        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $user->first_monthly_service_fee_on);
    }

}
