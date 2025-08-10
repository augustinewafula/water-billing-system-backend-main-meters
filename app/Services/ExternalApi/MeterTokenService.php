<?php

namespace App\Services\ExternalApi;

use App\Enums\MeterType;
use App\Models\MeterToken;
use App\Services\Meters\PrismMeterService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class MeterTokenService
{
    private const SERVICE_FEE_PERCENT = 8;

    public function __construct(
        private readonly PrismMeterService $prismMeterService
    ) {}
    /**
     * @throws RequestException
     */
    public function generateMeterToken($data): string
    {
        $token = $this->prismMeterService->generatePrismToken($data['meter_number'], $data['amount'], $data['units'], MeterType::from($data['meter_type']));

        if ($token === null) {
            throw new \RuntimeException('Failed to generate meter token');
        }

        MeterToken::create([
            'meter_number' => $data['meter_number'],
            'amount' => $data['amount'],
            'units' => $data['units'],
            'meter_type' => $data['meter_type'],
            'token' => $token,
            'user_id' => Auth()->user()->id,
            'service_fee_rule' => self::SERVICE_FEE_PERCENT . '%',
            'service_fee_applied' => $data['amount'] * (self::SERVICE_FEE_PERCENT / 100),
        ]);

        return $token;
    }

    /**
     * Clear token for a specific meter
     *
     * @param string $meterNumber
     * @return string|null
     * @throws RequestException
     */
    public function clearToken(string $meterNumber): ?string
    {
        Log::info('Initiating clear token operation', [
            'meter_number' => $meterNumber,
            'service' => 'MeterTokenService',
        ]);

        try {
            $token = $this->prismMeterService->clearPrismCredit($meterNumber);

            if ($token) {
                Log::info('Clear token operation completed successfully', [
                    'meter_number' => $meterNumber,
                    'token_received' => !empty($token),
                ]);
            } else {
                Log::warning('Clear token operation completed but no token received', [
                    'meter_number' => $meterNumber,
                ]);
            }

            return $token;

        } catch (RequestException $e) {
            Log::error('Clear token operation failed with RequestException', [
                'meter_number' => $meterNumber,
                'error' => $e->getMessage(),
                'response_body' => $e->response?->body(),
            ]);

            throw $e;

        } catch (\Exception $e) {
            Log::error('Clear token operation failed with unexpected error', [
                'meter_number' => $meterNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Clear tamper for a specific meter
     *
     * @param string $meterNumber
     * @return string|null
     * @throws RequestException
     */
    public function clearTamper(string $meterNumber): ?string
    {
        Log::info('Initiating clear tamper operation', [
            'meter_number' => $meterNumber,
            'service' => 'MeterTokenService',
        ]);

        try {
            $token = $this->prismMeterService->clearPrismTamper($meterNumber);

            if ($token) {
                Log::info('Clear tamper operation completed successfully', [
                    'meter_number' => $meterNumber,
                    'token_received' => !empty($token),
                ]);
            } else {
                Log::warning('Clear tamper operation completed but no token received', [
                    'meter_number' => $meterNumber,
                ]);
            }

            return $token;

        } catch (RequestException $e) {
            Log::error('Clear tamper operation failed with RequestException', [
                'meter_number' => $meterNumber,
                'error' => $e->getMessage(),
                'response_body' => $e->response?->body(),
            ]);

            throw $e;

        } catch (\Exception $e) {
            Log::error('Clear tamper operation failed with unexpected error', [
                'meter_number' => $meterNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

}
