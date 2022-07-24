<?php

namespace App\Actions;

use App\Models\ConnectionFee;
use App\Models\MpesaTransaction;
use App\Models\User;
use App\Traits\ProcessConnectionFeeTransaction;
use Carbon\Carbon;
use Throwable;

class GenerateConnectionFeeAction
{
    use ProcessConnectionFeeTransaction;

    /**
     * @throws Throwable
     */
    public function execute(User $user): void
    {
        \Log::info('Generating connection fee for user: ' . $user->id);
        $numberOfMonthsToBill = $user->number_of_months_to_pay_connection_fee;
        $billPerMonth = round($user->connection_fee / $numberOfMonthsToBill);

        $monthToGenerate = Carbon::createFromFormat('Y-m', $user->first_connection_fee_on)->startOfMonth()->startOfDay();

        for ($i = 0; $i < $numberOfMonthsToBill; $i++) {
            $currentMonth = Carbon::now()->startOfMonth()->startOfDay();
            $connectionFee = ConnectionFee::create([
                'user_id' => $user->id,
                'month' => $monthToGenerate,
                'amount' => $billPerMonth
            ]);

            if ($user->account_balance <= 0 && $monthToGenerate->lessThanOrEqualTo($currentMonth)){
                $user->update([
                    'account_balance' => ($user->account_balance - $billPerMonth)
                ]);
                $connectionFee->update([
                    'added_to_user_total_debt' => true
                ]);
            }
            $monthToGenerate = $monthToGenerate->add(1, 'month');
        }
        if ($user->account_balance > 0 && (!$this->hasCompletedConnectionFeePayment($user->id) && $this->hasMonthlyConnectionFeeDebt($user->id))) {
            $mpesa_transaction = MpesaTransaction::find($user->last_mpesa_transaction_id);
            $this->storeConnectionFeeBill($user->id, $mpesa_transaction, 0, 0, 0);
        }

    }

}
