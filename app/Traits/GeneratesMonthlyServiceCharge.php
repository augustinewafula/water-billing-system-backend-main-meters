<?php

namespace App\Traits;

use App\Models\MonthlyServiceCharge;
use App\Models\MpesaTransaction;
use Carbon\Carbon;

trait GeneratesMonthlyServiceCharge
{
    use ProcessesMonthlyServiceChargeTransaction;

    /**
     * @throws \Throwable
     */
    public function generateUserMonthlyServiceCharge($user, $monthly_service_charge): void
    {

        $firstDayOfCurrentMonth = Carbon::now()->startOfMonth();
        $monthToGenerate = $this->fromMonth($user);
        while ($monthToGenerate->lessThanOrEqualTo($firstDayOfCurrentMonth)) {
            MonthlyServiceCharge::create([
                'user_id' => $user->id,
                'month' => $monthToGenerate,
                'service_charge' => $monthly_service_charge
            ]);
            $user->update([
                'account_balance' => ($user->account_balance - $monthly_service_charge)
            ]);
            $monthToGenerate->add(1, 'month');
        }
        if ($user->account_balance > 0 && $this->hasMonthlyServiceChargeDebt($user->id)) {
            $mpesa_transaction = MpesaTransaction::find($user->last_mpesa_transaction_id);
            $this->storeMonthlyServiceCharge($user->id, $mpesa_transaction, 0);
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
