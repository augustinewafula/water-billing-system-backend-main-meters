<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateMeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Models\Meter;
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
     * Store a newly created resource in storage.
     *
     * @param CreateMeterRequest $request
     * @return JsonResponse
     */
    public function store(CreateMeterRequest $request): JsonResponse
    {
        $meter = Meter::create($request->validated());
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
