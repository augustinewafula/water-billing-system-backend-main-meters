<?php

namespace App\Http\Controllers;

use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Log;
use Throwable;

class StatisticsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $users = User::role('user')
                ->count();
            $mainMeters = Meter::where('main_meter', true)
                ->count();
            $meters = Meter::where('main_meter', false)
                ->count();
            $billingsSum = MeterBilling::sum('amount_paid');
        } catch (Throwable $throwable) {
            Log::error($throwable);
        }

        return response()->json([
            'users' => $users,
            'main_meters' => $mainMeters,
            'meters' => $meters,
            'revenue' => $billingsSum
        ]);
    }
}
