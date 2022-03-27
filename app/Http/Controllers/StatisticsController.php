<?php

namespace App\Http\Controllers;

use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterToken;
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
            $tokenSum = MeterToken::select('meter_tokens.*', 'mpesa_transactions.TransAmount')
                ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')->sum('TransAmount');
        } catch (Throwable $throwable) {
            Log::error($throwable);
            $response = ['message' => 'Something went wrong'];
            return response()->json($response, 422);
        }

        return response()->json([
            'users' => $users,
            'main_meters' => $mainMeters,
            'meters' => $meters,
            'revenue' => $billingsSum + $tokenSum
        ]);
    }
}
