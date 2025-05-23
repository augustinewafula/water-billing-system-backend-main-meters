<?php

namespace App\Traits;

use App\Enums\PrepaidMeterType;
use App\Jobs\GenerateMeterTokenJob;
use App\Jobs\SendSMS;
use App\Models\Meter;
use App\Models\MeterCharge;
use App\Models\MeterToken;
use App\Models\MpesaTransaction;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Throwable;

trait ProcessesPrepaidMeterTransaction
{
    use CalculatesUserAmount, UpdatesUserAccountBalance, NotifiesUser;


    /**
     * @param $meter_id
     * @param $mpesa_transaction
     * @param $deductions
     * @return void
     * @throws Throwable
     */
    private function processPrepaidTransaction($meter_id, $mpesa_transaction, $deductions): void
    {
        $user = User::where('meter_id', $meter_id)->first();
        $mpesa_transaction_id = $mpesa_transaction->id;
        throw_if($user === null, RuntimeException::class, "Meter $meter_id has no user assigned");

        $user_total_amount = $this->calculateUserTotalAmount($user->account_balance, $mpesa_transaction->TransAmount, $deductions);

        Log::info('User total amount after deductions: '. $user_total_amount, [
            'user_id' => $user->id,
            'account_number' => $user->account_number ]);
        if ($user_total_amount <= 0) {
            $message = $this->constructNotEnoughAmountMessage($this->userTotalDebt($user), $deductions);
            $this->notifyUser(
                (object)['message' => $message, 'title' => 'Insufficient amount'],
                $user,
                'general',
                $mpesa_transaction->MSISDN
            );

            return;
        }
        $units = $this->calculateUnits($user_total_amount, $user);
        Log::info("$units: $units");
        if ($units < 0) {
            $user = $this->updateUserAccountBalance($user, $user_total_amount, $deductions, $mpesa_transaction_id);
            $message = $this->constructNotEnoughAmountMessage($this->userTotalDebt($user), $deductions);
            $this->notifyUser(
                (object)['message' => $message, 'title' => 'Insufficient amount'],
                $user,
                'general',
                $mpesa_transaction->MSISDN
            );
            return;
        }

        GenerateMeterTokenJob::dispatch($meter_id, $mpesa_transaction, $deductions, $user_total_amount, $user);

    }

    /**
     * @param $totalDebt
     * @param $deductions
     * @return string
     */
    private function constructNotEnoughAmountMessage($totalDebt, $deductions): string
    {
        $message = 'The amount you paid is insufficient to acquire tokens, ';
        if ($deductions->unaccounted_debt_deducted > 0) {
            $unaccounted_debt_deducted_formatted = number_format($deductions->unaccounted_debt_deducted);
            $message .= "Ksh $unaccounted_debt_deducted_formatted was deducted for your previous debt. ";
        }
        if ($deductions->monthly_service_charge_deducted > 0) {
            $monthly_service_charge_deducted_formatted = number_format($deductions->monthly_service_charge_deducted);
            $message .= "Ksh $monthly_service_charge_deducted_formatted was deducted for monthly service fee balance. ";
        }
        if ($deductions->monthly_service_charge_deducted > 0 && $deductions->connection_fee_deducted > 0) {
            $message .= 'And ';
        }
        if ($deductions->connection_fee_deducted > 0) {
            $connection_fee_deducted_formatted = number_format($deductions->connection_fee_deducted);
            $message .= "Ksh $connection_fee_deducted_formatted was deducted for connection fee balance. ";
        }
        $total_debt_formatted = number_format($totalDebt);
        $message .= "Your current pending debt is Ksh $total_debt_formatted.";
        return $message;
    }

    public function userTotalDebt($user){
        $debt = 0;
        if ($user->account_balance < 0){
            $debt += abs($user->account_balance);
        }
        $debt += $user->unaccounted_debt;

        return $debt;
    }

}
