<?php

namespace App\Http\Controllers;

use App\Models\MeterStation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use Str;
use Throwable;

class MeterStationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $meter_stations = MeterStation::query();
        try {
            $meter_stations = $this->filterQuery($request, $meter_stations);
        } catch (Throwable $throw) {
            Log::error($throw);
        }
        return response()->json($meter_stations);
    }

    private function filterQuery(Request $request, Builder $meter_stations)
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');

        if ($request->has('search') && Str::length($search) > 0) {
            $meter_stations->where('name', 'like', '%' . $search . '%');
        }
        if ($request->has('sortBy')) {
            $meter_stations = $meter_stations->orderBy($sortBy, $sortOrder);
        }
        if ($request->has('paginate')) {
            return $meter_stations->withCount('meters')
                ->paginate(10);
        }
        return $meter_stations->get();
    }
}
