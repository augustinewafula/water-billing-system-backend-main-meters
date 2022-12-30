<?php

namespace App\Http\Controllers;

use App\Enums\MeterMode;
use App\Enums\ValveStatus;
use App\Http\Requests\CreateMainMeterRequest;
use App\Http\Requests\CreateMeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Jobs\GetMeterReadings;
use App\Models\FaultyMeter;
use App\Models\Meter;
use App\Models\MeterType;
use App\Traits\FiltersRequestQuery;
use App\Traits\GetsUserConnectionFeeBalance;
use App\Traits\ProcessesPrepaidMeterTransaction;
use App\Traits\TogglesValveStatus;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Log;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Str;
use Throwable;

class MeterController extends Controller
{
    use ProcessesPrepaidMeterTransaction, TogglesValveStatus, GetsUserConnectionFeeBalance, FiltersRequestQuery;

    public function __construct()
    {
        $this->middleware('permission:meter-list', ['only' => ['index', 'availableIndex', 'show']]);
        $this->middleware('permission:meter-create', ['only' => ['store']]);
        $this->middleware('permission:meter-edit', ['only' => ['update']]);
        $this->middleware('permission:meter-delete', ['only' => ['destroy']]);
        $this->middleware('permission:meter-type-list', ['only' => ['typeIndex']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws JsonException
     */
    public function index(Request $request): JsonResponse
    {
        $meters = Meter::with('user', 'station', 'type');
        $meters = $this->filterQuery($request, $meters);

        $perPage = 10;
        if ($request->has('perPage')){
            $perPage = $request->perPage;
        }
        return response()->json($meters->paginate($perPage));
    }

    public function availableIndex(Request $request): JsonResponse
    {
        $stationId = $request->query('station_id');
        $search = $request->query('search');

        $meters = Meter::query();
        if ($request->has('station_id')) {
            $meters->where('station_id', $stationId);
            $meters->whereMainMeter(false);
        }
        if ($request->has('isAvailable')) {
            $meters = $meters->doesntHave('user');
        }
        if ($request->has('search')) {
            $meters = $meters->where('number', 'like', '%' . $search . '%');
        }
        return response()->json($meters->paginate(10));
    }

    public function faultyIndex(Request $request): JsonResponse
    {
        $meters = FaultyMeter::with('meter', 'meter.user', 'meter.type');
        $perPage = 10;
        $search_filter = $request->query('search_filter');
        $search = $request->query('search');
        if ($request->has('perPage')){
            $perPage = $request->perPage;
        }
        if ($request->has('search') && Str::length($search) > 0) {
            $meters = $this->searchEagerLoadedQuery($meters, $search, $search_filter);
        }
        return response()->json($meters->paginate($perPage));
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

    public function showMeterTypeByNameIndex($meter_type_name): JsonResponse
    {
        $meter_type = MeterType::select('id', 'name')
            ->where('name', $meter_type_name)
            ->firstOrFail();
        return response()->json($meter_type);
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
        $response = $this->save($request);

        return response()->json($response['message'], $response['status_code']);
    }

    /**
     * @throws JsonException
     */
    public function storeMainMeter(CreateMainMeterRequest $request): JsonResponse
    {
        $response = $this->save($request);

        return response()->json($response['message'], $response['status_code']);
    }

    /**
     * @throws JsonException
     */
    public function save($request): array
    {
        if ((int)$request->mode === MeterMode::AUTOMATIC) {
            if ($this->isPrepaidMeter($request->type_id)) {
                $this->registerPrepaidMeter($request->number);
            }
            $validated = $request->validated();
            if ($request->has_location_coordinates) {
                $validated['lat'] = $request->location_coordinates['lat'];
                $validated['lng'] = $request->location_coordinates['lng'];
            }

            $meter = Meter::create($validated);
            if (!$this->isPrepaidMeter($request->type_id)) {
                GetMeterReadings::dispatch('daily');
            }
            return ['message' => $meter, 'status_code' => 201];
        }
        $main_meter = $request->main_meter;
        if (!$main_meter) {
            $main_meter = false;
        }
        $data = [
            'number' => $request->number,
            'station_id' => $request->station_id,
            'last_reading' => $request->last_reading,
            'mode' => $request->mode,
            'main_meter' => $main_meter,
            'location' => $request->location,
            'has_location_coordinates' => $request->has_location_coordinates,
        ];
        if ($request->has_location_coordinates) {
            $data['lat'] = $request->location_coordinates['lat'];
            $data['lng'] = $request->location_coordinates['lng'];
        }
        $meter = Meter::create($data);

        return ['message' => $meter, 'status_code' => 201];

    }

    private function isPrepaidMeter($type_id): bool
    {
        return MeterType::find($type_id)->name === 'Prepaid';
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
            ->firstOrFail();
        if ($meter->user && $meter->user->should_pay_connection_fee){
            $meter->user->connection_fee_balance = $this->getUserConnectionFeeBalance($meter->user);
        }
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
        $data = [
            'number' => $request->number,
            'type_id' => $request->type_id,
            'station_id' => $request->station_id,
            'mode' => $request->mode,
            'has_location_coordinates' => $request->has_location_coordinates,
            'location' => $request->location,
            'last_reading' => $request->last_reading,
        ];
        if ($request->has_location_coordinates) {
            $data['lat'] = $request->location_coordinates['lat'];
            $data['lng'] = $request->location_coordinates['lng'];
        }
        $meter->update($data);
        try {
            if ($request->number !== $meter->number && MeterType::find($request->type_id)->name === 'Prepaid') {
                $this->registerPrepaidMeter($meter->number);
            }
        } catch (Throwable $exception) {
            Log::error('Failed to register prepaid meter id: ' . $meter->id);
        }

        return response()->json($meter);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Meter $meter
     * @return Application|ResponseFactory|JsonResponse|Response
     * @throws JsonException
     */
    public function updateValveStatus(Request $request, Meter $meter)
    {
        $request->validate([
            'valve_status' => ['required', new EnumValue(ValveStatus::class, false)],
        ]);
        if (!$this->toggleValve($meter, $request->valve_status)) {
            $response = ['message' => 'Failed, please contact website admin for help'];
            return response($response, 422);
        }

        $valve_last_switched_off_by = 'system';
        if ((int)$request->valve_status === ValveStatus::CLOSED) {
            $valve_last_switched_off_by = 'user';
        }
        $meter->update([
            'valve_status' => $request->valve_status,
            'valve_last_switched_off_by' => $valve_last_switched_off_by
        ]);
        return response()->json($meter->number);
    }

    public function updateCanGenerateTokenStatus(Request $request, Meter $meter): JsonResponse
    {
        $request->validate([
            'can_generate_token' => ['required', 'boolean'],
        ]);
        $meter->update([
            'can_generate_token' => $request->can_generate_token,
        ]);
        return response()->json($meter->number);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Meter $meter
     * @return JsonResponse
     */
    public function destroy(Meter $meter): JsonResponse
    {
        $meter->forceDelete();
        return response()->json('deleted');
    }

    /**
     * @throws JsonException
     */
    private function filterQuery(Request $request, Builder $meters): Builder
    {
        $search = $request->query('search');
        $search_filter = $request->query('search_filter');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');
        $valveStatus = $request->query('valveStatus');

        if ($request->has('search') && Str::length($search) > 0) {
            $meters = $this->searchEagerLoadedQuery($meters, $search, $search_filter);
        }
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
        if ($request->has('valveStatus')) {
            $decoded_status = json_decode($valveStatus, false, 512, JSON_THROW_ON_ERROR);
            if (!empty($decoded_status)){
                $meters = $meters->whereIn('valve_status', $decoded_status);
            }

        }
        return $meters;
    }
}
