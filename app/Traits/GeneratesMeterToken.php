<?php

namespace App\Traits;

use App\Enums\MeterCategory;
use App\Enums\PrepaidMeterType;
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
    public function generateMeterToken(string $meter_number, float $amount, int $meterCategory, int $cost_per_unit, int $meterType = PrepaidMeterType::SH, ? float $units = null): ?string
    {
        if ($meterCategory === MeterCategory::WATER) {
            if ($meterType === PrepaidMeterType::SH) {
                $response = $this->generateWaterToken($meter_number, $amount, $cost_per_unit);
            } else {
                $response = $this->genererateCalinMeterToken($meter_number, $amount, $cost_per_unit);
            }

            return $response;
        }

        return $this->generateEnergyToken($meter_number, $amount, $units);

    }

    /**
     * @throws JsonException
     */
    private function generateWaterToken($meter_number, $amount, $cost_per_unit)
    {

        $api_token = env('PREPAID_METER_API_TOKEN');
        if (empty($api_token)) {
            $api_token = $this->loginPrepaidMeter();
        }

        $response = Http::retry(2, 100)
            ->post($this->baseUrl . 'vending', [
                'CustomerId' => $meter_number,
                'MeterId' => $meter_number,
                'Price' => $cost_per_unit,
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

    /**
     * @throws JsonException
     */
    private function genererateCalinMeterToken($meter_number, $amount, $cost_per_unit)
    {
        $response = Http::retry(2, 100)
            ->post("http://47.90.189.157:6001/api/POS_Purchase", [
                'company_name' => env('CALIN_METER_COMPANY'),
                'user_name' => env('CALIN_METER_USERNAME'),
                'password' => env('CALIN_METER_PASSWORD'),
                'password_vend' => env('CALIN_METER_PASSWORD'),
                'meter_number' => $meter_number,
                'is_vend_by_unit' => true,
                'amount' => (int)$amount,
            ]);
        if ($response->successful()) {
            Log::info('calin vending response:' . $response->body());

            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->result->token;
        }

        return null;
    }

    private function generateEnergyToken($meter_number, $amount, $units)
    {
        $response = Http::retry(2, 100)
            ->post('http://www.server-api.stronpower.com/api/VendingMeter', [
                'CompanyName' => env('PREPAID_ENERGY_METER_COMPANY'),
                'UserName' => env('PREPAID_ENERGY_METER_USERNAME'),
                'PassWord' => env('PREPAID_ENERGY_METER_PASSWORD'),
                'MeterID' => $meter_number,
                'is_vend_by_unit' => 'true',
                'Amount' => $units,
            ]);
        if ($response->successful()) {
            \Illuminate\Support\Facades\Log::info('vending response:' . $response->body());

            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)[0]->Token;
        }
        return null;
    }

}
