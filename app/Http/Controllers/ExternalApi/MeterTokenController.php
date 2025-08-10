<?php

namespace App\Http\Controllers\ExternalApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalRequests\ClearTamperRequest;
use App\Http\Requests\ExternalRequests\ClearTokenRequest;
use App\Http\Requests\ExternalRequests\StoreMeterTokenRequest;
use App\Services\ExternalApi\MeterTokenService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Traits\ApiResponse;

class MeterTokenController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MeterTokenService $meterTokenService
    ) {}

    /**
     * Generate Meter Token
     *
     * Generate a new meter token for electricity or water vending. This endpoint creates
     * a token that can be entered into the meter to top up units.
     *
     * @group Meter Tokens
     * @authenticated
     * @urlParam meter_number string required The meter number (max 20 characters). Example: MTR123456789
     * @urlParam units numeric required The number of units to purchase (must be greater than 0). Example: 100.50
     * @urlParam amount numeric required The amount being paid (must be greater than 0). Example: 1500.00
     * @urlParam meter_type string required The type of meter (water or energy). Example: energy
     *
     * @response 200 scenario="Token generated successfully" {
     *   "success": true,
     *   "message": "Token generated successfully.",
     *   "data": {
     *     "token": "12345678901234567890"
     *   },
     *   "errors": null
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "success": false,
     *   "message": "Validation failed.",
     *   "data": null,
     *   "errors": {
     *     "type": "ValidationException",
     *     "details": {
     *       "meter_number": ["The meter number is required."],
     *       "units": ["Units must be greater than 0."],
     *       "amount": ["Amount must be a number."],
     *       "meter_type": ["Meter type must be either water or energy."]
     *     }
     *   }
     * }
     *
     * @response 500 scenario="Token generation failed" {
     *   "success": false,
     *   "message": "Failed to generate meter token.",
     *   "data": null,
     *   "errors": {
     *     "type": "ServerError",
     *     "details": "External service error or system failure"
     *   }
     * }
     */
    public function vend(StoreMeterTokenRequest $request): JsonResponse
    {
        try {
            $token = $this->meterTokenService->generateMeterToken($request->validated());

            return $this->successResponse('Token generated successfully.', [
                'token' => $token,
            ]);
        } catch (Throwable $e) {
            Log::error('Token generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to generate meter token.', $e->getMessage());
        }
    }

    /**
     * Clear Meter Token
     *
     * Clear existing token from a meter to reset it to default state. This operation
     * removes any previously loaded token and resets the meter's token memory.
     *
     * @group Meter Tokens
     * @authenticated
     * @urlParam meter_number string required The meter number to clear token from (max 20 characters). Example: MTR123456789
     *
     * @response 200 scenario="Token cleared successfully" {
     *   "success": true,
     *   "message": "Token cleared successfully.",
     *   "data": {
     *     "meter_number": "MTR123456789",
     *     "token": "00000000000000000000"
     *   },
     *   "errors": null
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "success": false,
     *   "message": "Validation failed.",
     *   "data": null,
     *   "errors": {
     *     "type": "ValidationException",
     *     "details": {
     *       "meter_number": ["Meter number is required."]
     *     }
     *   }
     * }
     *
     * @response 422 scenario="Operation failed" {
     *   "success": false,
     *   "message": "Failed to clear token. Please try again.",
     *   "data": null,
     *   "errors": {
     *     "type": "OperationFailed",
     *     "details": null
     *   }
     * }
     *
     * @response 503 scenario="Service unavailable" {
     *   "success": false,
     *   "message": "Unable to connect to meter service.",
     *   "data": null,
     *   "errors": {
     *     "type": "ServiceUnavailable",
     *     "details": "Connection timeout or service down"
     *   }
     * }
     *
     * @response 500 scenario="Unexpected error" {
     *   "success": false,
     *   "message": "An unexpected error occurred while clearing token.",
     *   "data": null,
     *   "errors": {
     *     "type": "ServerError",
     *     "details": "System error or database connection issue"
     *   }
     * }
     */
    public function clearToken(ClearTokenRequest $request): JsonResponse
    {
        $meterNumber = $request->validated('meter_number');

        try {
            Log::info('Clear token request initiated', [
                'meter_number' => $meterNumber,
                'user_id' => auth()->id(),
            ]);

            $token = $this->meterTokenService->clearToken($meterNumber);

            if (!$token) {
                Log::warning('Token not cleared', [
                    'meter_number' => $meterNumber,
                ]);

                return $this->errorResponse(
                    'Failed to clear token. Please try again.',
                    null,
                    422,
                    'OperationFailed'
                );
            }

            Log::info('Token cleared successfully', [
                'meter_number' => $meterNumber,
                'token_length' => strlen($token),
            ]);

            return $this->successResponse('Token cleared successfully.', [
                'meter_number' => $meterNumber,
                'token' => $token,
            ]);
        } catch (RequestException $e) {
            Log::error('Meter service unavailable', [
                'meter_number' => $meterNumber,
                'error' => $e->getMessage(),
                'response' => $e->response?->body(),
            ]);

            return $this->errorResponse(
                'Unable to connect to meter service.',
                $e->getMessage(),
                503,
                'ServiceUnavailable'
            );
        } catch (Throwable $e) {
            Log::error('Unexpected error clearing token', [
                'meter_number' => $meterNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'An unexpected error occurred while clearing token.',
                $e->getMessage()
            );
        }
    }

    /**
     * Clear Meter Tamper
     *
     * Clear tamper status from a meter to restore normal operation. When a meter
     * detects tampering (such as unauthorized access or interference), it enters
     * a tamper state that prevents normal operation. This endpoint generates a
     * special token to clear the tamper flag and restore meter functionality.
     *
     * @group Meter Tokens
     * @authenticated
     * @urlParam meter_number string required The meter number to clear tamper from (max 20 characters). Example: MTR123456789
     *
     * @response 200 scenario="Tamper cleared successfully" {
     *   "success": true,
     *   "message": "Tamper cleared successfully.",
     *   "data": {
     *     "meter_number": "MTR123456789",
     *     "token": "99999999999999999999"
     *   },
     *   "errors": null
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "success": false,
     *   "message": "Validation failed.",
     *   "data": null,
     *   "errors": {
     *     "type": "ValidationException",
     *     "details": {
     *       "meter_number": ["Meter number is required."]
     *     }
     *   }
     * }
     *
     * @response 422 scenario="Operation failed" {
     *   "success": false,
     *   "message": "Failed to clear tamper. Please try again.",
     *   "data": null,
     *   "errors": {
     *     "type": "OperationFailed",
     *     "details": null
     *   }
     * }
     *
     * @response 503 scenario="Service unavailable" {
     *   "success": false,
     *   "message": "Unable to connect to meter service.",
     *   "data": null,
     *   "errors": {
     *     "type": "ServiceUnavailable",
     *     "details": "Connection timeout or service down"
     *   }
     * }
     *
     * @response 500 scenario="Unexpected error" {
     *   "success": false,
     *   "message": "An unexpected error occurred while clearing tamper.",
     *   "data": null,
     *   "errors": {
     *     "type": "ServerError",
     *     "details": "System error or database connection issue"
     *   }
     * }
     */
    public function clearTamper(ClearTamperRequest $request): JsonResponse
    {
        $meterNumber = $request->validated('meter_number');

        try {
            Log::info('Clear tamper request initiated', [
                'meter_number' => $meterNumber,
                'user_id' => auth()->id(),
            ]);

            $token = $this->meterTokenService->clearTamper($meterNumber);

            if (!$token) {
                Log::warning('Tamper not cleared', [
                    'meter_number' => $meterNumber,
                ]);

                return $this->errorResponse(
                    'Failed to clear tamper. Please try again.',
                    null,
                    422,
                    'OperationFailed'
                );
            }

            Log::info('Tamper cleared successfully', [
                'meter_number' => $meterNumber,
                'token_length' => strlen($token),
            ]);

            return $this->successResponse('Tamper cleared successfully.', [
                'meter_number' => $meterNumber,
                'token' => $token,
            ]);
        } catch (RequestException $e) {
            Log::error('Meter service unavailable', [
                'meter_number' => $meterNumber,
                'error' => $e->getMessage(),
                'response' => $e->response?->body(),
            ]);

            return $this->errorResponse(
                'Unable to connect to meter service.',
                $e->getMessage(),
                503,
                'ServiceUnavailable'
            );
        } catch (Throwable $e) {
            Log::error('Unexpected error clearing tamper', [
                'meter_number' => $meterNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'An unexpected error occurred while clearing tamper.',
                $e->getMessage()
            );
        }
    }
}

