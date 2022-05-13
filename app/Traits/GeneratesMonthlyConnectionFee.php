<?php

namespace App\Traits;

use App\Models\ConnectionFee;
use App\Models\MpesaTransaction;
use Carbon\Carbon;
use Throwable;

trait GeneratesMonthlyConnectionFee
{
    use ProcessConnectionFeeTransaction;
    /**
     * @throws Throwable
     */
    public function generateUserMonthlyConnectionFee($user, $monthly_connection_fee): void
    {

        $firstDayOfCurrentMonth = Carbon::now()->startOfMonth();
        $monthToGenerate = $this->getFirstMonthToGenerate($user);
        \Log::info($monthToGenerate);
        while ($monthToGenerate->lessThanOrEqualTo($firstDayOfCurrentMonth)) {
            ConnectionFee::create([
                'user_id' => $user->id,
                'month' => $monthToGenerate,
                'amount' => $monthly_connection_fee
            ]);
            if ($user->account_balance <= 0){
                $user->update([
                    'account_balance' => ($user->account_balance - $monthly_connection_fee)
                ]);
            }
            $monthToGenerate->add(1, 'month');
        }
        if ($user->account_balance > 0 && (!$this->hasCompletedConnectionFeePayment($user->id) && $this->hasMonthlyConnectionFeeDebt($user->id))) {
            $mpesa_transaction = MpesaTransaction::find($user->last_mpesa_transaction_id);
            $this->storeConnectionFee($user->id, $mpesa_transaction, 0, 0);
        }
    }

    public function getFirstMonthToGenerate($user)
    {
        $last_monthly_service_charge = ConnectionFee::where('user_id', $user->id)
            ->latest('month')
            ->limit(1)
            ->first();

        if ($last_monthly_service_charge) {
            return Carbon::createFromFormat('Y-m', $last_monthly_service_charge->month)->add(1, 'month');

        }

        return Carbon::createFromFormat('Y-m', $user->first_connection_fee_on)->startOfMonth()->startOfDay();
    }

}
