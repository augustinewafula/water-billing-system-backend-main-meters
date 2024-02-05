<?php

namespace App\Traits;

use App\Models\MpesaTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

trait UpdatesUserAccountBalance
{
    private function updateUserAccountBalance(
        User $user,
        $user_total_amount,
        $deductions,
        $mpesa_transaction_id=null,
        $token_consumed=false): User
    {
        $deductions_sum = $deductions->monthly_service_charge_deducted +
            $deductions->connection_fee_deducted +
            $deductions->unaccounted_debt_deducted;

        try {
            DB::beginTransaction();
            if ($token_consumed) {
                $user->account_balance = 0;
            }else {
                $user->account_balance = ($user->account_balance + $user_total_amount) +
                    ($deductions_sum - $deductions->unaccounted_debt_deducted);
            }
            $user->last_mpesa_transaction_id = $mpesa_transaction_id;
            $user->save();
            if ($mpesa_transaction_id){
                MpesaTransaction::find($mpesa_transaction_id)->update([
                    'Consumed' => true,
                ]);
            }
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error($throwable);
        }

        return $user;
    }

}
