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
    use AuthenticatesMeter, CalculatesBill, CalculatesUserTotalAmount;

    protected $baseUrl = 'http://www.shometersapi.stronpower.com/api/';

    /**
     * @throws JsonException
     */
    public function registerPrepaidMeter($meter_id): void
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'Meter', [
                'METER_ID' => $meter_id,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'METER_TYPE' => 1,
                'ApiToken' => $this->loginPrepaidMeter(),
            ]);
        Log::info('register response:' . $response->body());
    }

    /**
     * @param $meter_id
     * @param $mpesa_transaction
     * @param $monthly_service_charge_deducted
     * @return void
     * @throws Throwable
     */
    private function processPrepaidTransaction($meter_id, $mpesa_transaction, $monthly_service_charge_deducted): void
    {
        $user = User::where('meter_id', $meter_id)->first();
        throw_if($user === null, RuntimeException::class, "Meter $meter_id has no user assigned");

        $user_total_amount = $this->calculateUserTotalAmount($user->account_balance, $mpesa_transaction->TransAmount, $monthly_service_charge_deducted);

        if ($user_total_amount <= 0) {
            $message = "Your paid amount is not enough to purchase tokens, Ksh $monthly_service_charge_deducted was deducted for monthly service fee balance.";
            SendSMS::dispatch($mpesa_transaction->MSISDN, $message, $user->id);
            return;
        }
        $units = $this->calculateUnits($user_total_amount);
        if ($units < 0) {
            $message = 'Your paid amount is not enough to purchase tokens. ';
            if ($monthly_service_charge_deducted > 0) {
                $message .= "Ksh $monthly_service_charge_deducted was deducted for monthly service fee balance.";
            }
            try {
                DB::beginTransaction();
                $user->update([
                    'account_balance' => $user_total_amount,
                    'last_mpesa_transaction_id' => $mpesa_transaction->id
                ]);
                MpesaTransaction::find($mpesa_transaction->id)->update([
                    'Consumed' => true,
                ]);
                DB::commit();
            } catch (Throwable $throwable) {
                DB::rollBack();
                Log::error($throwable);
            }
            SendSMS::dispatch($mpesa_transaction->MSISDN, $message, $user->id);
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
                'meter_id' => $user->meter_id,
            ]);
            $user->update([
                'account_balance' => 0
            ]);
            MpesaTransaction::find($mpesa_transaction->id)->update([
                'Consumed' => true,
            ]);
            $date = Carbon::now()->toDateTimeString();
            $message = "Meter: $user->meter_number\nToken: $token\nUnits: $units\nAmount: $mpesa_transaction->TransAmount\nDate: $date\nRef: $mpesa_transaction->TransID";
            SendSMS::dispatch($mpesa_transaction->MSISDN, $message, $user->id);
            DB::commit();

        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error($throwable);
        }
    }

    /**
     * @throws JsonException
     */
    public function generateMeterToken($meter_number, $amount): ?string
    {
        $api_token = env('PREPAID_METER_API_TOKEN');
        if (empty($api_token)){
            $api_token = $this->loginPrepaidMeter();
        }
        $prepaid_meter_charges = MeterCharge::where('for', 'prepay')
            ->first();

        $response = Http::post($this->baseUrl . 'vending', [
                'CustomerId' => $meter_number,
                'MeterId' => $meter_number,
                'Price' => (int)$prepaid_meter_charges->cost_per_unit,
                'Rate' => 1,
                'Amount' => (int)$amount,
                'AmountTmp' => 'KES',
                'Company' => env('PREPAID_METER_COMPANY'),
                'Employee' => '0000',
                'ApiToken' => $api_token,
            ]);
        if ($response->successful()) {
            Log::info('vending response:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }
}
