<?php

namespace App\Services;

use App\Enums\PrepaidMeterType;
use App\Models\Meter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class ConcentratorService
{
    protected string $baseUrl = 'http://www.server-api.stronpower.com/api/';

    /**
     * @throws JsonException
     */
    public function register(string $concentrator_id, string $name): string
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'Meter', [
                'CompanyName' => env('CONCENTRATOR_COMPANY'),
                'UserName' => env('CONCENTRATOR_USERNAME'),
                'PassWord' => env('CONCENTRATOR_PASSWORD'),
                'ConcentratorID' => $concentrator_id,
                'ConcentratorName' => $name,
            ]);
        Log::info('concentrator register response:' . $response->body());

        return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
    }

    public function registerMeterWithConcentrator(string $meter_number, string $customer_id, string $concentrator_id): mixed
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'NewAmiMeter', [
                'MeterID' => $meter_number,
                'MeterID_End' => $meter_number,
                'CompanyName' => env('PREPAID_METER_COMPANY'),
                'UserName' => env('PREPAID_METER_USERNAME'),
                'PassWord' => env('PREPAID_METER_PASSWORD'),
                'MeterType' => 1,
                'CustomerID' => $customer_id,
                'ConcentratorID' => $concentrator_id,
            ]);
        Log::info('prepaid meter with concentrator register response:' . $response->body());

        return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
    }

    public function sendMeterToken(Meter $meter, string $token): bool
    {

        $meterNumber = $meter->number;
        if ($meter->prepaid_meter_type === PrepaidMeterType::CALIN) {
            return $this->sendCalinMeterToken($meterNumber, $token);
        }
        return $this->sendStronMeterToken($meterNumber, $token);
    }

    public function sendCalinMeterToken(string $meterNumber, string $token): bool
    {
        try {
            $response = Http::timeout(80)
                ->retry(3, 150)
                ->post('http://47.90.189.157:6001/api/COMM_RemoteToken', [
                    'CompanyName' => env('CALIN_CONCENTRATOR_COMPANY'),
                    'UserName' => env('CALIN_CONCENTRATOR_USERNAME'),
                    'PassWord' => env('CALIN_CONCENTRATOR_PASSWORD'),
                    'MeterNo' => $meterNumber,
                    'Token' => $token,
                ]);

            $responseBody = $response->body();
            Log::info('calin concentrator send meter token response:' . $responseBody);

            // Check both HTTP status and response message
            if (!$response->successful()) {
                Log::error('HTTP request failed with status: ' . $response->status());
                return false;
            }

            // Check for error messages in the response body
            $errorMessages = ['Recharge failed!', 'Failed', 'Error']; // Add other error messages as needed
            foreach ($errorMessages as $errorMessage) {
                if (str_contains($responseBody, $errorMessage)) {
                    Log::error('Token sending failed with message: ' . $responseBody);
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Exception while sending calin meter token: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param string $meterNumber
     * @param string $token
     * @return bool
     */
    private function sendStronMeterToken(string $meterNumber, string $token): bool
    {
        try {
            $response = Http::timeout(80)
                ->retry(3, 150)
                ->post($this->baseUrl . 'VendingMeterSendToken', [
                    'CompanyName' => env('CONCENTRATOR_COMPANY'),
                    'UserName' => env('CONCENTRATOR_USERNAME'),
                    'PassWord' => env('CONCENTRATOR_PASSWORD'),
                    'MeterID' => $meterNumber,
                    'Token' => $token,
                ]);

            $responseBody = $response->body();
            Log::info('concentrator send meter token response:' . $responseBody);

            // Check both HTTP status and response message
            if (!$response->successful()) {
                Log::error('HTTP request failed with status: ' . $response->status());
                return false;
            }

            // Check for error messages in the response body
            $errorMessages = ['Recharge failed!', 'Failed', 'Error']; // Add other error messages as needed
            foreach ($errorMessages as $errorMessage) {
                if (str_contains($responseBody, $errorMessage)) {
                    Log::error('Token sending failed with message: ' . $responseBody);
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Exception while sending meter token: ' . $e->getMessage());
            return false;
        }
    }

}
