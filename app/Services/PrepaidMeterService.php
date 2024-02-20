<?php

namespace App\Services;

use App\Enums\MeterCategory;
use App\Enums\PrepaidMeterType;
use App\Traits\AuthenticatesMeter;
use App\Traits\SetsEnvironmentalValue;
use Http;
use JsonException;
use Log;

class PrepaidMeterService
{
    use AuthenticatesMeter, SetsEnvironmentalValue;
    protected string $baseUrl = 'http://www.shometersapi.stronpower.com/api/';

    /**
     * @throws JsonException
     */
    public function registerPrepaidMeter(string $meter_number, int $prepaid_meter_type, MeterCategory $meterCategory): string
    {
        $response = '';
        if ($prepaid_meter_type === PrepaidMeterType::SH) {
            $response = $this->registerSHMeter($meter_number);
        }
        if ($prepaid_meter_type === PrepaidMeterType::CALIN) {
            // TODO: implement calin meter registration. For now, we'll just return a dummy response because the calin meter registration api is not yet implemented
//            $response = $this->registerCalinMeter($meter_number);
            $response = 'Calin Meter registered successfully';
        }
        if ($prepaid_meter_type === PrepaidMeterType::GOMELONG) {
            $response = $this->registerGomelongMeter($meter_number, $meterCategory);
        }
        return $response;
    }

    /**
     * @throws JsonException
     */
    public function registerSHMeter(string $meter_number)
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'Meter', [
                'METER_ID' => $meter_number,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'METER_TYPE' => 1,
                'REMARK' => 'production',
                'ApiToken' => $this->loginPrepaidMeter(),
            ]);
        \Illuminate\Support\Facades\Log::info('prepaid meter register response:' . $response->body());

        return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    public function registerCalinMeter(string $meter_number)
    {
        $response = Http::retry(3, 100)
            ->post('https://ami.calinhost.com/api/POS_Meter', [
                'company_name' => env('CALIN_METER_COMPANY'),
                'user_name' => env('CALIN_METER_USERNAME'),
                'password' => env('CALIN_METER_PASSWORD'),
                'customer_number' => $meter_number,
                'meter_number' => $meter_number,
                'customer_name' => $meter_number,
            ]);
        Log::info('calin prepaid meter register response:' . $response->body());

        return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    public function registerGomelongMeter(string $meter_number, MeterCategory $meterCategory)
    {
        // ENERGY => 1, WATER => 2
        $meterType = $meterCategory->value === MeterCategory::ENERGY ? 1 : 2;
        $response = Http::retry(3, 100)
            ->post('http://120.26.4.119:9094/api/Power/MeterRegister', [
                'UserId' => env('GOMELONG_METER_USERNAME'),
                'Password' => env('GOMELONG_METER_PASSWORD'),
                'UserTypeId' => 1,
                'MeterCode' => $meter_number,
                'MeterType' => $meterType,
            ]);
        Log::info('gomelong prepaid meter register response:' . $response->body());


        $jsonResponse = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);

        return $jsonResponse->Message;
    }

    /**
     * @throws JsonException
     */
    public function clearTamperRecord(string $meter_number, int $meterCategory, int $prePaidMeterType = PrepaidMeterType::SH): ?string
    {
        if ($meterCategory === MeterCategory::WATER) {
            if ($prePaidMeterType === PrepaidMeterType::SH) {
                return $this->clearWaterTamper($meter_number);
            }

            if ($prePaidMeterType === PrepaidMeterType::GOMELONG) {
                return $this->clearGomelongTamper($meter_number);
            }

            return $this->clearCalinMeterTamper($meter_number);
        }
        if ($prePaidMeterType === PrepaidMeterType::GOMELONG) {
            return $this->clearGomelongTamper($meter_number);
        }

        return $this->clearEnergyTamper($meter_number);

    }

    private function clearWaterTamper($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post($this->baseUrl . 'ClearTamper', [
                'CustomerId' => $meter_number,
                'METER_ID' => $meter_number,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'Employee' => '0000',
            ]);
        if ($response->successful()) {
            Log::info('clear tamper response water meter:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

    private function clearCalinMeterTamper($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post('http://47.90.189.157:6001/api/Maintenance_ClearTamper', [
                'company_name' => env('CALIN_METER_COMPANY'),
                'user_name' => env('CALIN_METER_USERNAME2'),
                'password' => env('CALIN_METER_PASSWORD'),
                'meter_number' => $meter_number,
            ]);
        if ($response->successful()) {
            Log::info('clear credit response calin meter:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->result;
        }
        return null;
    }

    /**
     * @throws JsonException
     */
    private function clearEnergyTamper($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post('http://www.server-api.stronpower.com/api/ClearTamper', [
                'CompanyName' => env('PREPAID_ENERGY_METER_COMPANY'),
                'UserName' => env('PREPAID_ENERGY_METER_USERNAME'),
                'PassWord' => env('PREPAID_ENERGY_METER_PASSWORD'),
                'Meter_ID' => $meter_number,
                'CustomerId' => $meter_number,
            ]);
        if ($response->successful()) {
            Log::info('clear tamper response energy meter:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

    /**
     * @throws JsonException
     */
    private function clearGomelongTamper($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post('http://120.26.4.119:9094/api/Power/GetClearTamperSignToken', [
                'UserId' => env('GOMELONG_METER_USERNAME'),
                'Password' => env('GOMELONG_METER_PASSWORD'),
                'MeterCode' => $meter_number,
            ]);
        if ($response->successful()) {
            Log::info('gomelong clear tamper response:' . $response->body());
            $jsonResponse = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);

            return $jsonResponse->Data;
        }
        return null;
    }


    /**
     * @throws JsonException
     */
    public function generateMeterToken(string $meter_number, float $amount, int $meterCategory, int $cost_per_unit, int $meterType = PrepaidMeterType::SH, ? float $units = null): ?string
    {
        if ($meterCategory === MeterCategory::WATER) {
            $response = '';
            if ($meterType === PrepaidMeterType::SH) {
                $response = $this->generateWaterToken($meter_number, $amount, $cost_per_unit);
            }

            if ($meterType === PrepaidMeterType::CALIN) {
                $response = $this->genererateCalinMeterToken($meter_number, $amount, $units);
            }

            if ($meterType === PrepaidMeterType::GOMELONG) {
                $response = $this->generateGomelongToken($meter_number, $amount, $units, MeterCategory::fromValue($meterCategory));
            }

            return $response;
        }

        if ($meterType === PrepaidMeterType::GOMELONG) {
            $response = $this->generateGomelongToken($meter_number, $amount, $units, MeterCategory::fromValue($meterCategory));
        } else {
            $response = $this->generateEnergyToken($meter_number, $amount, $units);
        }

        return $response;

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
        Log::info('vending response:' . $response->body());
        if ($response->successful()) {
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
    private function generateGomelongToken($meter_number, $amount, $units, MeterCategory $meterCategory)
    {
        Log::info("Starting generateGomelongToken function with meter_number: {$meter_number}, amount: {$amount}, units: {$units}, meterCategory: {$meterCategory->value}");
        // ENERGY => 1, WATER => 2
        $meterType = $meterCategory->value === MeterCategory::ENERGY ? 1 : 2;
        $response = Http::retry(2, 100)
            ->post("http://120.26.4.119:9094/api/Power/GetVendingToken", [
                'UserId' => env('GOMELONG_METER_USERNAME'),
                'Password' => env('GOMELONG_METER_PASSWORD'),
                'MeterCode' => $meter_number,
                'MeterType' => $meterType,
                'AmountOrQuantity' => $units,
                'VendingType' => 1, // 0 for amount, 1 for quantity
            ]);
        Log::info('gomelong vending response:' . $response->body());
        if ($response->successful()) {
            $jsonResponse = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);

            return $jsonResponse->Data->Token;
        }
        return null;
    }

    /**
     * @throws JsonException
     */
    private function genererateCalinMeterToken($meter_number, $amount, $units)
    {
        $response = Http::retry(2, 100)
            ->post("http://47.90.189.157:6001/api/POS_Purchase", [
                'company_name' => env('CALIN_METER_COMPANY'),
                'user_name' => env('CALIN_METER_USERNAME'),
                'password' => env('CALIN_METER_PASSWORD'),
                'password_vend' => env('CALIN_METER_PASSWORD'),
                'meter_number' => $meter_number,
                'is_vend_by_unit' => true,
                'amount' => $units,
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
