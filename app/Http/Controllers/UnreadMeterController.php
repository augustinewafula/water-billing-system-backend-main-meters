<?php

namespace App\Http\Controllers;

use App\Actions\DeleteUnreadMeter;
use App\Http\Requests\CreateUnreadMeterRequest;
use App\Http\Requests\UpdateUnreadMeterRequest;
use App\Models\UnreadMeter;
use App\Traits\FiltersRequestQuery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Str;

class UnreadMeterController extends Controller
{
    use FiltersRequestQuery;

    public function index(Request $request): JsonResponse
    {
        $unreadMeters = UnreadMeter::select('unread_meters.*')->with(['meter:id,number', 'user:id,meter_id,account_number,name']);
        $perPage = 10;
        if ($request->has('perPage')) {
            $perPage = $request->perPage;
        }
        $unreadMeters = $this->filterQuery($request, $unreadMeters);

        return response()->json($unreadMeters->paginate($perPage));
    }

    public function store(CreateUnreadMeterRequest $request): JsonResponse
    {
        $unreadMeter = UnreadMeter::create($request->validated());
        return response()->json($unreadMeter);
    }

    public function update(UpdateUnreadMeterRequest $request, UnreadMeter $unreadMeter): JsonResponse
    {
        $unreadMeter->update($request->validated());
        return response()->json($unreadMeter);
    }

    public function destroy(UnreadMeter $unreadMeter, DeleteUnreadMeter $deleteUnreadMeter): JsonResponse
    {
        $deleteUnreadMeter->execute($unreadMeter->id);
        return response()->json(['message' => 'Unread meter deleted successfully']);
    }

    private function filterQuery(Request $request, Builder $unreadMeters): Builder
    {
        $search = $request->query('search');
        $search_filter = $request->query('search_filter');
        $month = $request->query('month');
        $stationId = $request->query('station_id');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');

        if ($request->has('search') && Str::length($request->query('search')) > 0) {
            $unreadMeters = $this->searchEagerLoadedQuery($unreadMeters, $search, $search_filter);
        }
        if ($request->has('month') && !empty($request->query('month')) && $request->query('month') !== 'undefined') {
            $formattedFromDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
            $unreadMeters = $unreadMeters->whereMonth('month', $formattedFromDate);

        }
        if ($request->has('station_id')) {
            $unreadMeters = $unreadMeters->join('meters', 'meters.id', 'unread_meters.meter_id')
                ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
                ->where('meter_stations.id', $stationId);
        }

        return $unreadMeters;
    }
}
