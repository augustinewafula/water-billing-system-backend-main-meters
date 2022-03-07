<?php

namespace App\Http\Controllers;

use App\Models\MeterCharge;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::all();
        $meter_settings = MeterCharge::all();
        return response()->json([
            'settings' => $settings,
            'meter_settings' => $meter_settings,
        ]);
    }

    public function store(Request $request)
    {

    }
}
