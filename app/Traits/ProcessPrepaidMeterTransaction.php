<?php

namespace App\Traits;

use App\Jobs\SendSMS;
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

trait ProcessPrepaidMeterTransaction
{
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
                'ApiToken' => $this->login(),
            ]);
        Log::info('register response:' . $response->body());
    }

    /**
     * @throws JsonException
     */
    public function login(): ?string
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'login', [
                'Companyname' => env('PREPAID_METER_COMPANY'),
                'Username' => env('PREPAID_METER_USERNAME'),
                'Password' => env('PREPAID_METER_PASSWORD'),
            ]);
        if ($response->successful()) {
            Log::info('response:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

    /**
     * @param $meter_id
     * @param $content
     * @param $monthly_service_charge_deducted
     * @param $mpesa_transaction_id
     * @return void
     * @throws Throwable
     */
    private function processPrepaidTransaction($meter_id, $content, $monthly_service_charge_deducted, $mpesa_transaction_id): void
    {
        $user = User::where('meter_id', $meter_id)->first();
        throw_if($user === null, RuntimeException::class, "Meter $meter_id has no user assigned");
        $user_total_amount = $content->TransAmount - $monthly_service_charge_deducted;
        if ($user->account_balance > 0) {
            $user_total_amount += $user->account_balance;
        }
        if ($user_total_amount <= 0) {
            $message = "Your paid amount is not enough to purchase tokens, Ksh $monthly_service_charge_deducted was deducted for monthly service fee balance.";
            SendSMS::dispatch($content->MSISDN, $message, $user->id);
            return;
        }
        $units = $this->calculateUnits($user_total_amount);
        if ($units < 1) {
            $message = 'Your paid amount is not enough to purchase tokens. ';
            if ($monthly_service_charge_deducted > 0) {
                $message .= "Ksh $monthly_service_charge_deducted was deducted for monthly service fee balance.";
            }
            try {
                DB::beginTransaction();
                $user->update([
                    'account_balance' => $user_total_amount
                ]);
                MpesaTransaction::find($mpesa_transaction_id)->update([
                    'Consumed' => true,
                ]);
                DB::commit();
            } catch (Throwable $throwable) {
                DB::rollBack();
                Log::error($throwable);
            }
            SendSMS::dispatch($content->MSISDN, $message, $user->id);
            return;
        }

        try {
            DB::beginTransaction();
            $token = strtok($this->generateMeterToken($user->meter_number, $user_total_amount), ',');
            throw_if($token === null, RuntimeException::class, 'Failed to generate token');
            MeterToken::create([
                'mpesa_transaction_id' => $mpesa_transaction_id,
                'token' => strtok($token, ','),
                'units' => $units,
                'service_fee' => $this->calculateServiceFee($user_total_amount, 'prepay'),
                'monthly_service_charge_deducted' => $monthly_service_charge_deducted,
                'meter_id' => $user->meter_id,
            ]);
            $user->update([
                'account_balance' => 0
            ]);
            MpesaTransaction::find($mpesa_transaction_id)->update([
                'Consumed' => true,
            ]);
            $date = Carbon::now()->toDateTimeString();
            $message = "Meter: $user->meter_number\nToken: $token\nUnits: $units\nAmount: $content->TransAmount\nDate: $date\nRef: $content->TransID";
            SendSMS::dispatch($content->MSISDN, $message, $user->user_id);
            DB::commit();

        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error($throwable);
        }
    }

    /**
     * @throws JsonException
     */
    public function generateMeterToken($meter_id, $amount): ?string
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'vending', [
                'CustomerId' => $meter_id,
                'MeterId' => $meter_id,
                'Price' => 200,
                'Rate' => 1,
                'Amount' => $amount,
                'AmountTmp' => 'KES',
                'Company' => env('PREPAID_METER_COMPANY'),
                'Employee' => '0000',
                'ApiToken' => $this->login(),
            ]);
        if ($response->successful()) {
            Log::info('vending response:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }
}
