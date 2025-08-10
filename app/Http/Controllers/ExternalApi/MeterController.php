<?php

namespace App\Http\Controllers\ExternalApi;

use App\Enums\ValveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalRequests\GetMeterReadingRequest;
use App\Http\Requests\ExternalRequests\UpdateValveStatusRequest;
use App\Models\Meter;
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
     * @queryParam meter_number string required The unique meter number. Example: MTR123456789
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
    public function getMeterReadings(GetMeterReadingRequest $request): JsonResponse
    {
        $meterReadings = Meter::where('number', $request->meter_number)->firstOrFail();

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
}
