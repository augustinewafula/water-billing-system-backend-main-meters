<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateMeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Models\Meter;
use Illuminate\Http\JsonResponse;

class MeterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index()
    {
        $meters = Meter::all();
        return response()->json($meters);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateMeterRequest $request
     * @return JsonResponse
     */
    public function store(CreateMeterRequest $request)
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
    public function show(Meter $meter)
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
    public function update(UpdateMeterRequest $request, Meter $meter)
    {
        $meter->update($request->validated());
        return response()->json($meter, 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Meter $meter
     * @return JsonResponse
     */
    public function destroy(Meter $meter)
    {
        $meter->delete();
        return response()->json('deleted');
    }
}
