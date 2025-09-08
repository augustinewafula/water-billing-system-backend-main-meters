<?php

namespace App\Services;

use App\Models\HexingMeterCallbackLog;
use App\Models\Meter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HexingMeterService
{
    protected string $baseUrl;
    protected string $orgNo;
    protected string $sign;

    public function __construct()
    {
        $this->baseUrl = 'http://' . config('hexing.host') . ':' . config('hexing.port') . '/UIP/uip/uipManage/interfaceCode';
        $this->orgNo = config('hexing.org_no', '0');
        $this->sign = config('hexing.sign');
    }

    /**
     * Control valve (open/close/exit)
     * Based on ThirdValveWrite interface
     */
    public function controlValve(array $meterNumbers, string $valveAction): array
    {
        $url = $this->baseUrl . '/ThirdValveWrite/';

        // Convert valve action to API format
        $valveCommand = match($valveAction) {
            'open' => '1',
            'close' => '0',
            'exit' => '2',
            default => throw new \InvalidArgumentException("Invalid valve action: $valveAction")
        };

        $payload = [
            'meterCodes' => $meterNumbers,
            'valve' => $valveCommand,
            'orgNo' => $this->orgNo
        ];

        $queryParams = [
            'sign' => $this->sign,
            'timestamp' => '1756091050000'
//            'timestamp' => now()->timestamp * 1000
        ];

        try {
            $response = Http::timeout(30)
                ->post($url . '?' . http_build_query($queryParams), $payload);

            $responseData = $response->json();

            // Check if response data is valid
            if ($responseData === null) {
                throw new \Exception('Invalid or empty JSON response from Hexing API');
            }

            // Log requests for each meter
            foreach ($meterNumbers as $meterNumber) {
                $meter = Meter::where('number', $meterNumber)->first();
                if ($meter) {
                    $messageId = $this->extractMessageIdFromResponse($responseData, $meterNumber);
                    $this->createCallbackLog($meter, $messageId, 'valve-control', array_merge($payload, ['meter_number' => $meterNumber]));
                }
            }

            Log::info('Hexing valve control request sent', [
                'meter_codes' => $meterNumbers,
                'valve_action' => $valveAction,
                'response' => $responseData
            ]);

            return $responseData;

        } catch (\Exception $e) {
            $fullUrl = $url . '?' . http_build_query($queryParams);

            Log::error('Hexing valve control request failed', [
                'method' => 'controlValve',
                'request_url' => $fullUrl,
                'request_payload' => $payload,
                'valve_action' => $valveAction,
                'meter_codes' => $meterNumbers,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get real-time meter reading
     * Based on ThirdRealTimeDataRead interface
     */
    public function getRealTimeReading(array $meterNumbers): array
    {
        $url = $this->baseUrl . '/ThirdRealTimeDataRead/';

        $payload = [
            'meterCodes' => $meterNumbers,
            'orgNo' => $this->orgNo
        ];

        $queryParams = [
            'sign' => $this->sign,
            'timestamp' => '1756091050000'
        ];
        $fullUrl = $url . '?' . http_build_query($queryParams);

        try {
            $response = Http::timeout(30)
                ->post($url . '?' . http_build_query($queryParams), $payload);

            $responseData = $response->json();

            // Check if response data is valid
            if ($responseData === null) {
                throw new \Exception('Invalid or empty JSON response from Hexing API');
            }

            // Log requests for each meter
            foreach ($meterNumbers as $meterNumber) {
                $meter = Meter::where('number', $meterNumber)->first();
                if ($meter) {
                    $messageId = $this->extractMessageIdFromResponse($responseData, $meterNumber);
                    $this->createCallbackLog($meter, $messageId, 'meter-reading', array_merge($payload, ['meter_number' => $meterNumber]));
                }
            }

            Log::info('Hexing real-time reading request sent', [
                'meter_codes' => $meterNumbers,
                'request_url' => $fullUrl,
                'request_payload' => $payload,
                'response' => $responseData
            ]);

            return $responseData;

        } catch (\Exception $e) {

            Log::error('Hexing real-time reading request failed', [
                'method' => 'getRealTimeReading',
                'request_url' => $fullUrl,
                'request_payload' => $payload,
                'meter_codes' => $meterNumbers,
                'meter_count' => count($meterNumbers),
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Send token to meter
     * Based on ThirdTokenSet interface
     */
    public function sendToken(string $meterNumber, array $tokens): array
    {
        $url = $this->baseUrl . '/ThirdTokenSet/';

        $payload = [
            'meterCode' => $meterNumber,
            'tokens' => $tokens,
            'orgNo' => $this->orgNo
        ];

        $queryParams = [
            'sign' => $this->sign,
            'timestamp' => '1756091050000'
        ];

        try {
            $response = Http::timeout(30)
                ->post($url . '?' . http_build_query($queryParams), $payload);

            $responseData = $response->json();

            // Check if response data is valid
            if ($responseData === null) {
                throw new \Exception('Invalid or empty JSON response from Hexing API');
            }

            // Log request
            $meter = Meter::where('number', $meterNumber)->first();
            if ($meter) {
                $messageId = $this->extractMessageIdFromResponse($responseData, $meterNumber);
                $this->createCallbackLog($meter, $messageId, 'token', $payload);
            }

            Log::info('Hexing token send request sent', [
                'meter_code' => $meterNumber,
                'tokens' => $tokens,
                'response' => $responseData
            ]);

            return $responseData;

        } catch (\Exception $e) {
            $fullUrl = $url . '?' . http_build_query($queryParams);

            Log::error('Hexing token send request failed', [
                'method' => 'sendToken',
                'request_url' => $fullUrl,
                'request_payload' => $payload,
                'meter_code' => $meterNumber,
                'tokens' => $tokens,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Extract message ID from API response for a specific meter
     */
    private function extractMessageIdFromResponse(array $response, string $meterNumber): ?string
    {
        if (isset($response['data']) && is_array($response['data'])) {
            foreach ($response['data'] as $item) {
                if (isset($item['meterCode']) && $item['meterCode'] === $meterNumber) {
                    return $item['messageId'] ?? null;
                }
            }
        }
        return null;
    }

    /**
     * Create callback log entry
     */
    private function createCallbackLog(Meter $meter, ?string $messageId, string $action, array $payload): HexingMeterCallbackLog
    {
        return HexingMeterCallbackLog::create([
            'meter_id' => $meter->id,
            'message_id' => $messageId,
            'action' => $action,
            'request_payload' => $payload,
            'status' => 'pending',
            'sent_at' => now(),
        ]);
    }

    /**
     * Process callback response for valve control
     */
    public function processValveCallback(array $callbackData): void
    {
        $messageId = $callbackData['messageId'] ?? null;
        $valveStatus = $callbackData['valve'] ?? null;
        $dateTime = $callbackData['dateTime'] ?? null;

        if (!$messageId) {
            Log::warning('Hexing valve callback missing messageId', $callbackData);
            return;
        }

        $log = HexingMeterCallbackLog::where('message_id', $messageId)
            ->where('action', 'valve-control')
            ->first();

        if (!$log) {
            Log::warning('Hexing valve callback received for unknown message', [
                'message_id' => $messageId,
                'callback_data' => $callbackData
            ]);
            return;
        }

        $status = match($valveStatus) {
            '128' => 'open_success',
            '129' => 'close_success',
            '400' => 'timeout',
            default => 'unknown_status'
        };

        // Update meter valve_status in database
        $meter = $log->meter;
        if ($meter && in_array($status, ['open_success', 'close_success'])) {
            $meterValveStatus = match($status) {
                'open_success' => 'open',
                'close_success' => 'closed',
                default => $meter->valve_status
            };
            
            $meter->update([
                'valve_status' => $meterValveStatus,
                'last_communication_date' => now(),
            ]);
        }

        $log->update([
            'response_payload' => $callbackData,
            'status' => $status === 'timeout' ? 'failed' : 'completed',
            'callback_received_at' => now(),
        ]);

        Log::info('Hexing valve callback processed', [
            'message_id' => $messageId,
            'meter_id' => $log->meter_id,
            'valve_status' => $valveStatus,
            'status' => $status,
            'meter_valve_updated' => isset($meterValveStatus) ? $meterValveStatus : null,
            'callback_data' => $callbackData
        ]);
    }

    /**
     * Process callback response for real-time reading
     */
    public function processReadingCallback(array $callbackData): void
    {
        $messageId = $callbackData['messageId'] ?? null;
        $status = $callbackData['status'] ?? null;
        $data = $callbackData['data'] ?? null;

        if (!$messageId) {
            Log::warning('Hexing reading callback missing messageId', $callbackData);
            return;
        }

        $log = HexingMeterCallbackLog::where('message_id', $messageId)
            ->where('action', 'meter-reading')
            ->first();

        if (!$log) {
            Log::warning('Hexing reading callback received for unknown message', [
                'message_id' => $messageId,
                'callback_data' => $callbackData
            ]);
            return;
        }

        $callbackStatus = $status === '0' ? 'completed' : 'failed';

        // Update meter last_reading and last_reading_date in database
        $meter = $log->meter;
        if ($meter && $callbackStatus === 'completed' && !empty($data)) {
            $reading = $data['reading'] ?? $data['totalVolume'] ?? null;
            $readingDateTime = $data['readingTime'] ?? $data['dateTime'] ?? $callbackData['dateTime'] ?? null;
            
            $updateData = ['last_communication_date' => now()];
            
            if ($reading !== null) {
                $updateData['last_reading'] = $reading;
            }
            
            if ($readingDateTime) {
                try {
                    $updateData['last_reading_date'] = Carbon::parse($readingDateTime);
                } catch (\Exception $e) {
                    Log::warning('Failed to parse reading datetime', [
                        'datetime' => $readingDateTime,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                // If no specific reading time, use current time
                $updateData['last_reading_date'] = now();
            }
            
            $meter->update($updateData);
        }

        $log->update([
            'response_payload' => $callbackData,
            'status' => $callbackStatus,
            'callback_received_at' => now(),
        ]);

        Log::info('Hexing reading callback processed', [
            'message_id' => $messageId,
            'meter_id' => $log->meter_id,
            'status' => $status,
            'has_data' => !empty($data),
            'meter_reading_updated' => isset($reading) ? $reading : null,
            'callback_data' => $callbackData
        ]);
    }

    /**
     * Process callback response for token
     */
    public function processTokenCallback(array $callbackData): void
    {
        $messageId = $callbackData['messageId'] ?? null;
        $status = $callbackData['status'] ?? null;
        $dateTime = $callbackData['dateTime'] ?? null;

        if (!$messageId) {
            Log::warning('Hexing token callback missing messageId', $callbackData);
            return;
        }

        $log = HexingMeterCallbackLog::where('message_id', $messageId)
            ->where('action', 'token')
            ->first();

        if (!$log) {
            Log::warning('Hexing token callback received for unknown message', [
                'message_id' => $messageId,
                'callback_data' => $callbackData
            ]);
            return;
        }

        $callbackStatus = $status === '0' ? 'completed' : 'failed';

        $log->update([
            'response_payload' => $callbackData,
            'status' => $callbackStatus,
            'callback_received_at' => now(),
        ]);

        Log::info('Hexing token callback processed', [
            'message_id' => $messageId,
            'meter_id' => $log->meter_id,
            'status' => $status,
            'callback_data' => $callbackData
        ]);
    }
}
