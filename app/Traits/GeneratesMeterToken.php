<?php

namespace App\Traits;

use App\Models\MeterCharge;
use Http;
use JsonException;
use Log;

trait GeneratesMeterToken
{
    use AuthenticatesMeter, SetsEnvironmentalValue;
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

        $response = Http::retry(2, 100)
            ->post($this->baseUrl . 'vending', [
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
            if ($response->body() === ''){
                $this->setEnvironmentValue('PREPAID_METER_API_TOKEN', null);
            }
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

}
