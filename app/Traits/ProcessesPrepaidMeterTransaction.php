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
     * @param $monthly_service_charge_deducted
     * @param $connection_fee_deducted
     * @param $unaccounted_debt_deducted
     * @return void
     * @throws Throwable
     */
    private function processPrepaidTransaction($meter_id, $mpesa_transaction, $monthly_service_charge_deducted, $connection_fee_deducted, $unaccounted_debt_deducted): void
    {
        $user = User::where('meter_id', $meter_id)->first();
        $mpesa_transaction_id =
        throw_if($user === null, RuntimeException::class, "Meter $meter_id has no user assigned");

        $user_total_amount = $this->calculateUserTotalAmount($user->account_balance, $mpesa_transaction->TransAmount, $monthly_service_charge_deducted, $connection_fee_deducted, $unaccounted_debt_deducted);


        if ($user_total_amount <= 0) {
            $message = $this->constructNotEnoughAmountMessage($this->userTotalDebt($user), $monthly_service_charge_deducted, $connection_fee_deducted, $unaccounted_debt_deducted);
            $this->notifyUser((object)['message' => $message, 'title' => 'Insufficient amount'], $user, 'general');
            return;
        }
        $units = $this->calculateUnits($user_total_amount, $user);
        Log::info("$units: $units");
        if ($units < 0) {
            $message = $this->constructNotEnoughAmountMessage($this->userTotalDebt($user), $monthly_service_charge_deducted, $connection_fee_deducted, $unaccounted_debt_deducted);
            try {
                DB::beginTransaction();
                $user->update([
                    'account_balance' => $user_total_amount,
                    'last_mpesa_transaction_id' => $mpesa_transaction->id
                ]);

                if ($mpesa_transaction_id){
                    MpesaTransaction::find($mpesa_transaction->id)->update([
                        'Consumed' => true,
                    ]);
                }
                DB::commit();
            } catch (Throwable $throwable) {
                DB::rollBack();
                Log::error($throwable);
            }
            $this->notifyUser((object)['message' => $message, 'title' => 'Insufficient amount'], $user, 'general');
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
                'service_fee' => $this->calculateServiceFee($user_total_amount, 'prepay'),
                'monthly_service_charge_deducted' => $monthly_service_charge_deducted,
                'connection_fee_deducted' => $connection_fee_deducted,
                'unaccounted_debt_deducted' => $unaccounted_debt_deducted,
                'meter_id' => $user->meter_id,
            ]);
            $user->update([
                'account_balance' => 0
            ]);
            if ($mpesa_transaction_id){
                MpesaTransaction::find($mpesa_transaction->id)->update([
                    'Consumed' => true,
                ]);
            }
            $date = Carbon::now()->toDateTimeString();
            $message = "Meter: $meter_number\nToken: $token\nUnits: $units\nAmount: $user_total_amount\nAccount: $user->account_number\nDate: $date\nRef: $mpesa_transaction->TransID";

            $this->notifyUser((object)['message' => $message], $user, 'meter tokens');
            DB::commit();

        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error($throwable);
        }
    }

    /**
     * @param $totalDebt
     * @param $monthly_service_charge_deducted
     * @param $connection_fee_deducted
     * @param $unaccounted_debt_deducted
     * @return string
     */
    private function constructNotEnoughAmountMessage($totalDebt, $monthly_service_charge_deducted, $connection_fee_deducted, $unaccounted_debt_deducted): string
    {
        $message = 'The amount you paid is insufficient to acquire tokens, ';
        if ($unaccounted_debt_deducted > 0) {
            $unaccounted_debt_deducted_formatted = number_format($unaccounted_debt_deducted);
            $message .= "Ksh $unaccounted_debt_deducted_formatted was deducted for your previous debt. ";
        }
        if ($monthly_service_charge_deducted > 0) {
            $monthly_service_charge_deducted_formatted = number_format($monthly_service_charge_deducted);
            $message .= "Ksh $monthly_service_charge_deducted_formatted was deducted for monthly service fee balance. ";
        }
        if ($monthly_service_charge_deducted > 0 && $connection_fee_deducted > 0) {
            $message .= 'And ';
        }
        if ($connection_fee_deducted > 0) {
            $connection_fee_deducted_formatted = number_format($connection_fee_deducted);
            $message .= "Ksh $connection_fee_deducted_formatted was deducted for connection fee balance.";
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
