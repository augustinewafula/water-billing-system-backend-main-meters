<?php

namespace App\Http\Controllers;

use App\Enums\MeterMode;
use App\Enums\ValveStatus;
use App\Http\Requests\CreateMeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Models\Meter;
use App\Models\MeterType;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $station_id = $request->query('station_id');
        $meters = Meter::with('user', 'station', 'type');
        if ($request->has('station_id')) {
            $meters->where('station_id', $station_id);
        }
        $meters = $meters->get();
        return response()->json($meters);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function typeIndex(Request $request): JsonResponse
    {
        $meter_types = MeterType::all();
        return response()->json($meter_types);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateMeterRequest $request
     * @return JsonResponse
     */
    public function store(CreateMeterRequest $request): JsonResponse
    {
        if ($request->mode === MeterMode::Automatic) {
            $meter = Meter::create($request->validated());
            return response()->json($meter, 201);
        }
        $meter = Meter::create([
            'number' => $request->number,
            'station_id' => $request->station_id,
            'mode' => $request->mode
        ]);
        return response()->json($meter, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param Meter $meter
     * @return JsonResponse
     */
    public function show(Meter $meter): JsonResponse
    {
        return response()->json($meter);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateMeterRequest $request
     * @param Meter $meter
     * @return JsonResponse
     */
    public function update(UpdateMeterRequest $request, Meter $meter): JsonResponse
    {
        $meter->update($request->validated());
        return response()->json($meter);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Meter $meter
     * @return JsonResponse
     */
    public function updateValveStatus(Request $request, Meter $meter): JsonResponse
    {
        $request->validate([
            'valve_status' => ['required', new EnumValue(ValveStatus::class, false)],
        ]);
        $meter->update([
            'valve_status' => $request->valve_status
        ]);
        return response()->json($meter);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Meter $meter
     * @return JsonResponse
     */
    public function destroy(Meter $meter): JsonResponse
    {
        $meter->delete();
        return response()->json('deleted');
    }
}
