<?php

namespace App\Http\Controllers\ExternalApi;

use App\Enums\PrepaidMeterType;
use App\Enums\ValveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalRequests\UpdateValveStatusRequest;
use App\Models\ClientRequestContext;
use App\Models\DailyMeterReading;
use App\Models\Meter;
use App\Models\MeterType;
use App\Services\HexingMeterService;
use App\Traits\ApiResponse;
use App\Traits\TogglesValveStatus;
use Illuminate\Http\JsonResponse;
use JsonException;

class MeterController extends Controller
{
    use ApiResponse, TogglesValveStatus;

    /**
     * Get Meter Readings
     *
     * Retrieve the latest meter readings for a given meter number.
     *
     * @group Meters
     * @authenticated
     * @urlParam meter_number string required The unique meter number. Example: MTR123456789
     *
     * @response 200 scenario="Meter readings retrieved successfully" {
     *   "success": true,
     *   "message": "Current Meter Readings",
     *   "data": {
     *     "current_meter_readings": 345.67,
     *     "last_reading_date": "2025-08-09 12:34:56"
     *   },
     *   "errors": null
     * }
     *
     * @response 404 scenario="Meter not found" {
     *   "success": false,
     *   "message": "No query results for model [Meter]",
     *   "data": null,
     *   "errors": {
     *     "type": "ModelNotFoundException",
     *     "details": null
     *   }
     * }
     */
    public function getMeterReadings(string $meterNumber): JsonResponse
    {
        $meter = Meter::where('number', $meterNumber)->first();

        if (!$meter) {
            return $this->errorResponse(
                'Meter not found',
                null,
                404,
                'ModelNotFoundException'
            );
        }

        $latestDailyReading = DailyMeterReading::where('meter_id', $meter->id)
            ->latest()
            ->first();

        return $this->successResponse('Current Meter Readings', [
            'current_meter_readings' => $latestDailyReading?->reading,
            'last_reading_date' => $latestDailyReading?->created_at?->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update Meter Valve Status
     *
     * Toggle a meter's valve to either open or closed state.
     *
     * **Response Types:**
     * - **Request examples labeled "(Direct operation)"** are returned immediately after the request
     * - **Request examples labeled "(sent to your callback URL)"** represent payloads that will be delivered to your callback URL for asynchronous operations
     *
     * **Asynchronous Flow (for supported meter types):**
     * 1. Send the valve control request
     * 2. Receive immediate response with message_id and status: "pending"
     * 3. Wait for callback to your registered webhook URL with the final result
     *
     * **Callback URL Requirements:**
     * - Must accept HTTP POST requests
     * - Must respond with HTTP 200 status for successful delivery
     * - Should handle JSON payload as shown in callback examples below
     *
     * **Callback Security & Headers:**
     * Callbacks are sent as HTTP POST requests with these headers:
     * - Content-Type: application/json
     * - User-Agent: Hydro-Pro-Webhook/1.0
     * - X-Webhook-Signature: sha256=[signature] (if secret token configured)
     *
     * **Security:** If you've configured a secret token, verify the X-Webhook-Signature header using HMAC SHA256:
     * sha256(hmac(json_payload, your_secret_token))
     *
     * **Retry Policy:** Failed callback deliveries retry up to 3 times with intervals of 3 seconds, 10 seconds, and 30 seconds.
     *
     * @group Meters
     * @authenticated
     * @urlParam meter_number string required The meter number. Example: MTR123456789
     * @bodyParam valve_status integer required The desired valve status (1 for open, 0 for closed). Example: 1
     *
     * @response 200 scenario="Valve control request initiated successfully (Direct operation)" {
     *   "success": true,
     *   "message": "Valve control request initiated",
     *   "data": {
     *     "meter_number": "MTR123456789",
     *     "message_id": "MSG-2025091212345678",
     *     "requested_valve_status": "close",
     *     "message": "Request submitted successfully. Result will be delivered via callback.",
     *     "status": "pending"
     *   },
     *   "errors": null
     * }
     *
     * @response 404 scenario="Meter not found (Direct operation)" {
     *   "success": false,
     *   "message": "Meter not found",
     *   "data": null,
     *   "errors": {
     *     "type": "ModelNotFoundException",
     *     "details": null
     *   }
     * }
     *
     * @response 422 scenario="Valve operation failed (Direct operation)" {
     *   "success": false,
     *   "message": "Failed, please contact website admin for help",
     *   "data": null,
     *   "errors": {
     *     "type": "ValveOperationError",
     *     "details": null
     *   }
     * }
     *
     * @response 422 scenario="Failed to initiate valve control request (Direct operation)" {
     *   "success": false,
     *   "message": "Failed to initiate valve control request",
     *   "data": null,
     *   "errors": {
     *     "type": "ValveOperationError",
     *     "details": null
     *   }
     * }
     *
     * @response 500 scenario="Server error (Direct operation)" {
     *   "success": false,
     *   "message": "An unexpected error occurred while processing the valve control request",
     *   "data": null,
     *   "errors": {
     *     "type": "ServerError",
     *     "details": "Specific error message details"
     *   }
     * }
     *
     * @response 200 scenario="Callback - Valve Closed Successfully (sent to your callback URL)" {
     *   "success": true,
     *   "message": "Valve closed successfully",
     *   "data": {
     *     "event_type": "valve_status_update",
     *     "meter_number": "MTR123456789",
     *     "requested_action": "valve-control",
     *     "valve_status": "closed",
     *     "timestamp": "2025-09-12 10:02:19",
     *     "message_id": "17791"
     *   },
     *   "errors": null
     * }
     *
     * @response 200 scenario="Callback - Valve Opened Successfully (sent to your callback URL)" {
     *   "success": true,
     *   "message": "Valve opened successfully",
     *   "data": {
     *     "event_type": "valve_status_update",
     *     "meter_number": "MTR123456789",
     *     "requested_action": "valve-control",
     *     "valve_status": "open",
     *     "timestamp": "2025-09-12 10:02:19",
     *     "message_id": "17791"
     *   },
     *   "errors": null
     * }
     *
     * @response 200 scenario="Callback - Operation Timeout (sent to your callback URL)" {
     *   "success": false,
     *   "message": "Operation timed out",
     *   "data": {
     *     "event_type": "valve_status_update",
     *     "meter_number": "MTR123456789",
     *     "requested_action": "valve-control",
     *     "valve_status": "unknown",
     *     "timestamp": "2025-09-12 10:02:19",
     *     "message_id": "17791"
     *   },
     *   "errors": {
     *     "type": "CallbackError",
     *     "details": "Operation timed out"
     *   }
     * }
     *
     * @response 200 scenario="Callback - Operation Failed (sent to your callback URL)" {
     *   "success": false,
     *   "message": "Operation failed",
     *   "data": {
     *     "event_type": "valve_status_update",
     *     "meter_number": "MTR123456789",
     *     "requested_action": "valve-control",
     *     "valve_status": "unknown",
     *     "timestamp": "2025-09-12 10:02:19",
     *     "message_id": "17791"
     *   },
     *   "errors": {
     *     "type": "CallbackError",
     *     "details": "Operation failed"
     *   }
     * }
     *
     */
    public function updateValveStatus(UpdateValveStatusRequest $request, string $meterNumber): JsonResponse
    {
        try {
            $meter = Meter::where('number', $meterNumber)->firstOrFail();

            // Check if this is a Hexing meter
            if ($this->isHexingMeter($meter)) {
                return $this->handleHexingValveControl($meter, $request);
            }

            // Existing logic for non-Hexing meters
            if (!$this->toggleValve($meter, $request->valve_status)) {
                return $this->errorResponse(
                    'Failed, please contact website admin for help',
                    null,
                    422,
                    'ValveOperationError'
                );
            }

            $valveLastSwitchedOffBy = (int) $request->valve_status === ValveStatus::CLOSED
                ? 'user'
                : 'system';

            $meter->update([
                'valve_status' => $request->valve_status,
                'valve_last_switched_off_by' => $valveLastSwitchedOffBy
            ]);

            return $this->successResponse('Valve status updated successfully', [
                'meter_number' => $meter->number,
                'valve_status' => ValveStatus::getKey((int) $meter->valve_status),
                'valve_last_switched_off_by' => $meter->valve_last_switched_off_by
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse(
                'Meter not found',
                null,
                404,
                'ModelNotFoundException'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'An unexpected error occurred while processing the valve control request',
                $e->getMessage(),
                500,
                'ServerError'
            );
        }
    }

    private function isHexingMeter(Meter $meter): bool
    {
        $meterType = MeterType::find($meter->type_id);
        return $meterType && $meterType->name === 'Prepaid' && $meter->prepaid_meter_type === PrepaidMeterType::HEXING;
    }

    private function handleHexingValveControl(Meter $meter, UpdateValveStatusRequest $request): JsonResponse
    {
        $hexingService = app(HexingMeterService::class);
        $valveAction = (int) $request->valve_status === ValveStatus::OPEN ? 'open' : 'close';

        $response = $hexingService->controlValve([$meter->number], $valveAction);

        // Extract message ID for this specific meter from Hexing response
        $messageId = $this->extractMessageIdFromHexingResponse($response, $meter->number);

        if (!$messageId) {
            throw new \Exception('No message ID received from Hexing API for meter: ' . $meter->number);
        }

        // Store client request context for callback matching
        $this->storeClientCallbackContext($meter, $response, $request, $messageId);

        return $this->successResponse('Valve control request initiated', [
            'meter_number' => $meter->number,
            'message_id' => $messageId,
            'requested_valve_status' => $valveAction,
            'message' => 'Request submitted successfully. Result will be delivered via callback.',
            'status' => 'pending'
        ]);
    }

    private function extractMessageIdFromHexingResponse(array $response, string $meterNumber): ?string
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

    private function storeClientCallbackContext(Meter $meter, array $hexingResponse, UpdateValveStatusRequest $request, string $messageId): void
    {
        $clientId = auth()->user()->id;

        ClientRequestContext::create([
            'meter_id' => $meter->id,
            'client_id' => $clientId,
            'message_id' => $messageId,
            'action_type' => 'valve-control',
            'original_request' => [
                'meter_number' => $meter->number,
                'valve_status' => $request->valve_status,
                'requested_at' => now()->toISOString(),
            ],
            'hexing_response' => $hexingResponse,
            'status' => 'pending'
        ]);
    }
}
