<?php

namespace App\Traits;

use App\Enums\MeterCategory;
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
    public function generateMeterToken(string $meter_number, float $amount, int $meterCategory): ?string
    {
        if ($meterCategory === MeterCategory::WATER) {
            return $this->generateWaterToken($meter_number, $amount);
        }

        return $this->generateEnergyToken($meter_number, $amount);

    }

    /**
     * @throws JsonException
     */
    private function generateWaterToken($meter_number, $amount)
    {

        $api_token = env('PREPAID_METER_API_TOKEN');
        if (empty($api_token)) {
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
            if ($response->body() === '') {
                $this->setEnvironmentValue('PREPAID_METER_API_TOKEN', null);
            }
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

    private function generateEnergyToken($meter_number, $amount)
    {
        $prepaid_meter_charges = MeterCharge::where('for', 'prepay')
            ->first();

        $response = Http::retry(2, 100)
            ->post('http://www.server-api.stronpower.com/api/VendingMeter', [
                'CompanyName' => env('PREPAID_ENERGY_METER_COMPANY'),
                'UserName' => env('PREPAID_ENERGY_METER_USERNAME'),
                'PassWord' => env('PREPAID_ENERGY_METER_PASSWORD'),
                'MeterID' => $meter_number,
                'Price     ' => (int)$prepaid_meter_charges->cost_per_unit,
                'Amount' => (int)$amount,
            ]);
        if ($response->successful()) {
            Log::info('vending response:' . $response->body());

            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)[0]->Token;
        }
        return null;
    }

}
