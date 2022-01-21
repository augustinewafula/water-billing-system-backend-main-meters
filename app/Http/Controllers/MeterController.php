<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateMeterRequest;
use App\Models\Meter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(CreateMeterRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param Meter $meter
     * @return Response
     */
    public function show(Meter $meter)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Meter $meter
     * @return Response
     */
    public function update(Request $request, Meter $meter)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Meter $meter
     * @return Response
     */
    public function destroy(Meter $meter)
    {
        //
    }
}
