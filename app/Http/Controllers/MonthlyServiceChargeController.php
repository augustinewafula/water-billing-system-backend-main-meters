<?php

namespace App\Http\Controllers;

use App\Models\MonthlyServiceCharge;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Str;

class MonthlyServiceChargeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:monthly-service-charge-list', ['only' => ['index', 'show']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $monthly_service_charges = MonthlyServiceCharge::with('user');
        $monthly_service_charges = $this->filterQuery($request, $monthly_service_charges);
        return response()->json(
            $monthly_service_charges->paginate(10));
    }

    /**
     * Display the specified resource.
     *
     * @param $monthlyServiceChargeID
     * @return JsonResponse
     */
    public function show($monthlyServiceChargeID): JsonResponse
    {
        $monthly_service_charge = MonthlyServiceCharge::with('user', 'monthly_service_charge_payments')
            ->where('id', $monthlyServiceChargeID)
            ->first();
        return response()->json($monthly_service_charge);
    }

    public function filterQuery(Request $request, $monthly_service_charge)
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');
        $userId = $request->query('user_id');
        $fromDate = $request->query('fromDate');
        $toDate = $request->query('toDate');

        if ($request->has('station_id')) {
            $monthly_service_charge = $monthly_service_charge->select('monthly_service_charges.*')
                ->join('users', 'users.id', 'monthly_service_charges.user_id')
                ->join('meters', 'meters.id', 'users.meter_id')
                ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
                ->where('meter_stations.id', $stationId);
        }

        if ($request->has('user_id')) {
            $monthly_service_charge = $monthly_service_charge->select('monthly_service_charges.*')
                ->join('users', 'users.id', 'monthly_service_charges.user_id')
                ->where('users.id', $userId);
        }

        if (($request->has('fromDate') && Str::length($request->query('fromDate')) > 0) && ($request->has('toDate') && Str::length($request->query('toDate')) > 0)) {
            $formattedFromDate = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
            $formattedToDate = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
            $monthly_service_charge = $monthly_service_charge->whereBetween('monthly_service_charges.created_at', [$formattedFromDate, $formattedToDate]);
        }

//        if ($request->has('sortBy')) {
//            $monthly_service_charge = $monthly_service_charge->orderBy($sortBy, $sortOrder);
//        }

        return $monthly_service_charge;
    }
}
