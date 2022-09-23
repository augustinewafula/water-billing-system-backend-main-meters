<?php

namespace App\Actions;

use App\Models\ConnectionFee;
use App\Models\MpesaTransaction;
use App\Models\User;
use App\Traits\ProcessConnectionFeeTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Log;
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

        $monthToGenerate = Carbon::createFromFormat('Y-m-d', $user->first_connection_fee_on);

        for ($i = 0; $i < $numberOfMonthsToBill; $i++) {
            $user->refresh();
            $connectionFee = ConnectionFee::create([
                'user_id' => $user->id,
                'month' => $monthToGenerate,
                'amount' => $billPerMonth
            ]);

            if ($user->hasNoFundsInAccount() && $monthToGenerate->lessThanOrEqualTo(now())){
//                $this->debitConnectionFeeBillToUserAccount($user, $connectionFee, $billPerMonth);
            }
            $monthToGenerate = $monthToGenerate->add(1, 'month');

            if ($user->hasFundsInAccount() && (!$this->hasCompletedConnectionFeePayment($user->id) && $this->hasMonthlyConnectionFeeDebt($user->id))) {
                $this->processConnectionFeeBill($user->id, $user->last_mpesa_transaction_id);
            }
        }

    }

    private function debitConnectionFeeBillToUserAccount(User $user, ConnectionFee $connectionFee, $billPerMonth): User
    {
        DB::transaction(function () use ($user, $connectionFee, $billPerMonth) {
            $user->account_balance -= $billPerMonth;
            $user->save();

            $connectionFee->added_to_user_total_debt = true;
            $connectionFee->save();
        });

        return $user;
    }

    /**
     * @throws Throwable
     */
    private function processConnectionFeeBill(String $userId, String $lastMpesaTransactionId): void
    {
        $mpesa_transaction = MpesaTransaction::find($lastMpesaTransactionId);

        $deductions = new Collection();
        $deductions->monthly_service_charge_deducted = 0;
        $deductions->unaccounted_debt_deducted = 0;
        $deductions->connection_fee_deducted = 0;

        $this->storeConnectionFeeBill($userId, $mpesa_transaction, 0, $deductions, false, true);
    }

}
