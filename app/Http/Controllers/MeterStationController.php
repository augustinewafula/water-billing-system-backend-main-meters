<?php

namespace App\Http\Controllers;

use App\Models\MeterStation;

class MeterStationController extends Controller
{
    public function index()
    {
        return response()->json(MeterStation::all());
    }
}
