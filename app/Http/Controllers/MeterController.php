<?php

namespace App\Http\Controllers;

use App\Enums\MeterMode;
use App\Enums\ValveStatus;
use App\Http\Requests\CreateMeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Models\Meter;
use App\Models\MeterType;
use App\Traits\ProcessPrepaidMeterTransaction;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Log;
use Throwable;

class MeterController extends Controller
{
    use ProcessPrepaidMeterTransaction;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $meters = Meter::with('user', 'station', 'type');
        $meters = $this->filterQuery($request, $meters);
        return response()->json($meters->paginate(10));
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
     * @throws JsonException
     */
    public function store(CreateMeterRequest $request): JsonResponse
    {
        if ((int)$request->mode === MeterMode::Automatic) {
            $meter = Meter::create($request->validated());

            try {
                if (MeterType::find($request->type_id)->name === 'Prepaid') {
                    $this->register_meter($meter->id);
                }
            } catch (Throwable $exception) {
                Log::error('Failed to register prepaid meter id: ' . $meter->id);
            }
            return response()->json($meter, 201);
        }
        $meter = Meter::create([
            'number' => $request->number,
            'station_id' => $request->station_id,
            'last_reading' => $request->last_reading,
            'mode' => $request->mode
        ]);
        return response()->json($meter, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $meter = Meter::with('user', 'station', 'type')
            ->where('id', $id)
            ->first();
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
        $meter->update([
            'number' => $request->number,
            'station_id' => $request->station_id,
            'type_id' => $request->type_id,
            'valve_status' => $request->valve_status,
            'mode' => $request->mode
        ]);
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

    private function filterQuery(Request $request, Builder $meters): Builder
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');

        //TODO:: implement search
        if ($request->has('station_id')) {
            $meters->where('station_id', $stationId);
            $meters->whereMainMeter(false);
        }
        if ($request->has('main_meters')) {
            $meters->whereMainMeter(true);
        }
        if ($request->has('sortBy')) {
            $meters = $meters->orderBy($sortBy, $sortOrder);
        }
        return $meters;
    }
}
