<?php

namespace App\Services;

use App\Enums\MeterCategory;
use App\Enums\PrepaidMeterType;
use App\Traits\AuthenticatesMeter;
use App\Traits\SetsEnvironmentalValue;
use Http;
use Illuminate\Http\Client\RequestException;
use JsonException;
use Log;

class PrepaidMeterService
{
    use AuthenticatesMeter, SetsEnvironmentalValue;

    //stron power base url changed because generated token is not working when inputted, it returns 'OLD'
//    protected string $stronPowerBaseUrlUrl = 'http://www.shometersapi.stronpower.com/api/';
    protected string $stronPowerBaseUrlUrl = 'http://www.server-api.stronpower.com/api/';

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
        $baseUrl = $this->stronPowerBaseUrlUrl;

        // Data for shometersapi endpoint
        if (str_contains($baseUrl, 'shometersapi')) {
            $data = [
                'METER_ID' => $meter_number,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'METER_TYPE' => 1,
                'REMARK' => 'production',
                'ApiToken' => $this->loginPrepaidMeter(),
            ];
        }
        // Data for server-api endpoint
        else if (str_contains($baseUrl, 'server-api')) {
            $data = [
                'CompanyName' => env('PREPAID_METER_COMPANY'),
                'UserName' => env('PREPAID_METER_USERNAME'),
                'PassWord' => env('PREPAID_METER_PASSWORD'),
                'AccountID' => $meter_number,
                'CustomerID' => $meter_number,
                'CustomerName' => "PRO-$meter_number",
                'CustomerAddress' => '',
                'CustomerPhone' => '',
                'CustomerEmail' => '',
                'PriceCategories' => 'HomeChargePrice',
                'SalesStationID' => '0004',
                'MeterID' => $meter_number,
                'MeterType' => 1
            ];
        }

        $endpoint = str_contains($baseUrl, 'shometersapi') ? 'Meter' : 'NewCustomer';
        $response = Http::retry(3, 100)
            ->post($baseUrl . $endpoint, $data);

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
                'UserTypeId' => 2024021901,
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
    public function clearTamperRecord(string $meter_number, int $meterCategory, int $prePaidMeterType = PrepaidMeterType::SH, $usePrismVend = false): ?string
    {
        if ($usePrismVend) {
            return $this->clearPrismTamper($meter_number);
        }
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

    private function clearWaterTamper(string $meter_number)
    {
        $baseUrl = $this->stronPowerBaseUrlUrl;

        // Data for shometersapi endpoint
        if (str_contains($baseUrl, 'shometersapi')) {
            $data = [
                'CustomerId' => $meter_number,
                'METER_ID' => $meter_number,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'Employee' => '0000',
            ];
        }
        // Data for server-api endpoint
        else if (str_contains($baseUrl, 'server-api')) {
            $data = [
                'CompanyName' => env('PREPAID_METER_COMPANY'),
                'UserName' => env('PREPAID_METER_USERNAME'),
                'PassWord' => env('PREPAID_METER_PASSWORD'),
                'METER_ID' => $meter_number,
                'CustomerId' => $meter_number
            ];
        }

        $endpoint = str_contains($baseUrl, 'shometersapi') ? 'ClearTamper' : 'ClearTamper';
        $response = Http::retry(2, 100)
            ->post($baseUrl . $endpoint, $data);

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
    public function generateMeterToken(string $meter_number, float $amount, int $meterCategory, int $cost_per_unit, int $meterType = PrepaidMeterType::SH, ? float $units = null, $usePrismVend = false): ?string
    {
        if ($usePrismVend) {
            return $this->generatePrismToken($meter_number, $amount, $units, MeterCategory::fromValue($meterCategory));
        }
        if ($meterCategory === MeterCategory::WATER) {
            $response = '';
            if ($meterType === PrepaidMeterType::SH) {
                $response = $this->generateWaterToken($meter_number, $amount, $cost_per_unit, $units);
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
    private function generateWaterToken($meter_number, $amount, $cost_per_unit, ? float $units = null)
    {
        $baseUrl = $this->stronPowerBaseUrlUrl;

        // Data for shometersapi endpoint
        if (str_contains($baseUrl, 'shometersapi')) {
            $api_token = env('PREPAID_METER_API_TOKEN');
            if (empty($api_token)) {
                $api_token = $this->loginPrepaidMeter();
            }

            $data = [
                'CustomerId' => $meter_number,
                'MeterId' => $meter_number,
                'Price' => $cost_per_unit,
                'Rate' => 1,
                'Amount' => (int)$amount,
                'AmountTmp' => 'KES',
                'Company' => env('PREPAID_METER_COMPANY'),
                'Employee' => '0000',
                'ApiToken' => $api_token,
            ];
        }
        // Data for server-api endpoint
        else if (str_contains($baseUrl, 'server-api')) {
            $data = [
                'CompanyName' => env('PREPAID_METER_COMPANY'),
                'UserName' => env('PREPAID_METER_USERNAME'),
                'PassWord' => env('PREPAID_METER_PASSWORD'),
                'MeterID' => $meter_number,
                'is_vend_by_unit' => 'true',
                'Amount' => $units
            ];
        }

        $endpoint = str_contains($baseUrl, 'shometersapi') ? 'vending' : 'VendingMeter';
        $response = Http::retry(2, 100)
            ->post($baseUrl . $endpoint, $data);

        Log::info('vending response:' . $response->body());

        if ($response->successful()) {
            if ($response->body() === '' && str_contains($baseUrl, 'shometersapi')) {
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

        // Prepare the query parameters
        $queryParameters = [
            'UserId' => env('GOMELONG_METER_USERNAME'),
            'Password' => env('GOMELONG_METER_PASSWORD'),
            'MeterCode' => $meter_number,
            'MeterType' => $meterType,
            'AmountOrQuantity' => $units,
            'VendingType' => 1, // 0 for amount, 1 for quantity
        ];

        // Log the query parameters
        Log::info('Sending Gomelong vending request with parameters: ' . json_encode($queryParameters, JSON_THROW_ON_ERROR));

        $response = Http::retry(2, 100)
            ->get("http://120.26.4.119:9094/api/Power/GetVendingToken", $queryParameters);

        Log::info('Gomelong vending response: ' . $response->body());

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

    private function generatePrismToken(string $meter_number, float $amount, float $units, MeterCategory $meterCategory): ?string
    {
        $subclass = $meterCategory->value === MeterCategory::ENERGY ? '0' : '1';

        // Calculate the value: 1 unit = 10,000 values
        $value = $units * 10000;

        Log::info('Prism vending calculation:', [
            'meterId' => $meter_number,
            'units' => $units,
            'calculatedValue' => $value,
            'subclass' => $subclass,
            'meter category' => $meterCategory->description
        ]);

        try {
            $response = Http::retry(2, 1000)
                ->timeout(100)
                ->withBasicAuth(env('PRISM_USERNAME'), env('PRISM_PASSWORD'))
                ->acceptJson()
                ->asForm()
                ->post('http://41.209.60.94:8080/stsvend/VendCredit.xml', [
                    'meterId' => $meter_number,
                    'subclass' => $subclass,
                    'value' => $value, // Use the calculated value instead of units
                ]);

            // Log the response regardless of the status
            \Illuminate\Support\Facades\Log::info('Prism vending response:', [
                'meterId' => $meter_number,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            $response->throw();  // This will throw an exception for non-2xx responses

            if ($response->successful()) {
                $xml = simplexml_load_string($response->body());
                $token = (string)$xml->tokenDec;

                $formattedToken = chunk_split($token, 4, ' ');
                $formattedToken = rtrim($formattedToken);

                return $formattedToken;
            }
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Log the full exception details
            \Illuminate\Support\Facades\Log::error('Prism vending error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'response' => $e->response?->body(),
            ]);

            // You might want to rethrow the exception or handle it in some way
            throw $e;
        }

        return null;
    }

    /**
     * @throws RequestException
     */
    public function clearPrismCredit($meter_number): ?string
    {
        return $this->sendPrismClearRequest($meter_number, '1');
    }

    /**
     * @throws RequestException
     */
    private function clearPrismTamper($meter_number): ?string
    {
        return $this->sendPrismClearRequest($meter_number, '5');
    }

    /**
     * @throws RequestException
     */
    private function sendPrismClearRequest($meter_number, $subclass): ?string
    {
        try {
            $response = Http::retry(2, 1000)
                ->timeout(100)
                ->withBasicAuth(env('PRISM_USERNAME'), env('PRISM_PASSWORD'))
                ->acceptJson()
                ->asForm()
                ->post('http://41.209.60.94:8080/stsvend/VendMse.xml', [
                    'meterId' => $meter_number,
                    'subclass' => $subclass,
                ]);

            // Log the response regardless of the status
            \Illuminate\Support\Facades\Log::info('Prism clear response:', [
                'meterId' => $meter_number,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            $response->throw();  // This will throw an exception for non-2xx responses

            if ($response->successful()) {
                $xml = simplexml_load_string($response->body());
                $token = (string)$xml->tokenDec;

                $formattedToken = chunk_split($token, 4, ' ');
                $formattedToken = rtrim($formattedToken);

                return $formattedToken;
            }
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Log the full exception details
            \Illuminate\Support\Facades\Log::error('Prism clear request error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'response' => $e->response?->body(),
            ]);

            // You might want to rethrow the exception or handle it in some way
            throw $e;
        } catch (\Exception $e) {
            // Catch any other exceptions that might occur
            \Illuminate\Support\Facades\Log::error('Unexpected error in Prism clear request:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        return null;
    }

}
