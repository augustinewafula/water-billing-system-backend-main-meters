<?php

namespace App\Traits;

use App\Jobs\SendSMS;
use App\Models\Meter;
use App\Models\MeterCharge;
use App\Models\MeterToken;
use App\Models\MpesaTransaction;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Http;
use JsonException;
use Log;
use RuntimeException;
use Throwable;

trait ProcessesPrepaidMeterTransaction
{
    use AuthenticatesMeter, CalculatesBill, CalculatesUserAmount, GeneratesMeterToken, NotifiesUser;

    protected $baseUrl = 'http://www.shometersapi.stronpower.com/api/';

    /**
     * @throws JsonException
     */
    public function registerPrepaidMeter($meter_number): string
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'Meter', [
                'METER_ID' => $meter_number,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'METER_TYPE' => 1,
                'REMARK' => 'production',
                'ApiToken' => $this->loginPrepaidMeter(),
            ]);
        Log::info('prepaid meter register response:' . $response->body());
        return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
    }

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


        if ($user_total_amount <= 0) {
            $this->updateUserAccountBalance($user, $user_total_amount, $deductions, $mpesa_transaction_id);
            $message = $this->constructNotEnoughAmountMessage($this->userTotalDebt($user), $deductions);
            $this->notifyUser(
                (object)['message' => $message, 'title' => 'Insufficient amount'],
                $user,
                'general',
                $mpesa_transaction->MSISDN
            );

            return;
        }
        Log::info('User total amount after deductions: '. $user_total_amount);
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

        try {
            DB::beginTransaction();
            $meter_number = Meter::find($meter_id)->number;
            $token = $this->generateMeterToken($meter_number, $user_total_amount);
            throw_if($token === null || $token === '', RuntimeException::class, 'Failed to generate token');
            $token = strtok($token, ',');
            MeterToken::create([
                'mpesa_transaction_id' => $mpesa_transaction->id,
                'token' => strtok($token, ','),
                'units' => $units,
                'service_fee' => $this->calculateServiceFee($user, $user_total_amount, 'prepay'),
                'monthly_service_charge_deducted' => $deductions->monthly_service_charge_deducted,
                'connection_fee_deducted' => $deductions->connection_fee_deducted,
                'unaccounted_debt_deducted' => $deductions->unaccounted_debt_deducted,
                'meter_id' => $user->meter_id,
            ]);
            $this->updateUserAccountBalance($user, $user_total_amount, $deductions, $mpesa_transaction_id, true);

            $date = Carbon::now()->toDateTimeString();
            $message = "
            Meter: $meter_number\n
            Token: $token\n
            Units: $units\n
            Amount: $user_total_amount\n
            Account: $user->account_number\n
            Date: $date\n
            Ref: $mpesa_transaction->TransID";

            $this->notifyUser(
                (object)['message' => $message, 'title' => 'Water Meter Tokens'],
                $user,
                'meter tokens',
                $mpesa_transaction->MSISDN
            );
            DB::commit();

        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error($throwable);
            $this->notifyUser(
                (object)['message' => "Failed to generate token of Ksh {$mpesa_transaction->TransAmount} for your meter, please contact management for help.",
                    'title' => 'Insufficient amount'],
                $user,
                'general',
                $mpesa_transaction->MSISDN
            );
        }
    }

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
