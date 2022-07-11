<?php

namespace App\Traits;

use App\Models\MpesaTransaction;
use App\Models\UnaccountedDebt;
use App\Models\User;
use DB;
use Log;
use Throwable;

trait ProcessUnaccountedDebt
{
    public function hasUnaccountedDebt($user_unaccounted_debt):bool
    {
        return $user_unaccounted_debt > 0;
    }

    public function processUnaccountedDebt($user_id, $mpesa_transaction)
    {
        $user = User::findOrFail($user_id);
        $user_unaccounted_debt = $user->unaccounted_debt;
        $amount_deducted = $mpesa_transaction->TransAmount;
        if ($mpesa_transaction->TransAmount > $user_unaccounted_debt){
            $amount_deducted = $user_unaccounted_debt;
        }
        $amount_remaining = $user_unaccounted_debt - $amount_deducted;
        try {
            DB::beginTransaction();
            UnaccountedDebt::create([
                'user_id' => $user_id,
                'amount_paid'=> $mpesa_transaction->TransAmount,
                'amount_deducted' => $amount_deducted,
                'amount_remaining' => $amount_remaining,
                'mpesa_transaction_id' => $mpesa_transaction->id
            ]);
            $user->unaccounted_debt = $amount_remaining;
            $user->last_mpesa_transaction_id = $mpesa_transaction->id;
            $user->save();
            MpesaTransaction::find($mpesa_transaction->id)->update([
                'Consumed' => true,
            ]);
            DB::commit();

        } catch (Throwable $throwable) {
            DB::rollBack();
            $amount_deducted = 0;
            Log::error($throwable);
        }
        return $amount_deducted;
    }

}
