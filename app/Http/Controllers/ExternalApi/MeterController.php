<?php

namespace App\Http\Controllers\ExternalApi;

use App\Enums\PrepaidMeterType;
use App\Enums\ValveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalRequests\UpdateValveStatusRequest;
use App\Models\ClientRequestContext;
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
        $meterReadings = Meter::where('number', $meterNumber)->firstOrFail();

        return $this->successResponse('Current Meter Readings', [
            'current_meter_readings' => $meterReadings->last_reading,
            'last_reading_date' => $meterReadings->last_reading_date
        ]);
    }

    /**
     * Update Meter Valve Status
     *
     * Toggle a meter's valve to either open or closed state.
     *
     * @group Meters
     * @authenticated
     * @urlParam meter_number string required The meter number. Example: MTR123456789
     * @bodyParam valve_status integer required The desired valve status (1 for open, 0 for closed). Example: 1
     *
     * @response 200 scenario="Valve status updated successfully" {
     *   "success": true,
     *   "message": "Valve status updated successfully",
     *   "data": {
     *     "meter_number": "MTR123456789",
     *     "valve_status": "CLOSED",
     *     "valve_last_switched_off_by": "user"
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
     *
     * @response 422 scenario="Valve operation failed" {
     *   "success": false,
     *   "message": "Failed, please contact website admin for help",
     *   "data": null,
     *   "errors": {
     *     "type": "ValveOperationError",
     *     "details": null
     *   }
     * }
     *
     * @throws JsonException
     */
    public function updateValveStatus(UpdateValveStatusRequest $request, string $meterNumber): JsonResponse
    {
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
    }

    private function isHexingMeter(Meter $meter): bool
    {
        $meterType = MeterType::find($meter->type_id);
        return $meterType && $meterType->name === 'Prepaid' && $meter->prepaid_meter_type === PrepaidMeterType::HEXING;
    }

    private function handleHexingValveControl(Meter $meter, UpdateValveStatusRequest $request): JsonResponse
    {
        $hexingService = app(HexingMeterService::class);
        $valveAction = $request->valve_status === ValveStatus::OPEN ? 'open' : 'close';

        try {
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

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to initiate valve control request',
                null,
                422,
                'ValveOperationError'
            );
        }
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
