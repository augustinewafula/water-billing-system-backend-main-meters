<?php

namespace App\Http\Controllers;

use App\Models\ConnectionFee;
use App\Http\Controllers\Controller;
use App\Traits\GetsUserConnectionFeeBalance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Str;

class ConnectionFeeController extends Controller
{
    use GetsUserConnectionFeeBalance;

    public function __construct()
    {
        $this->middleware('permission:connection-fee-list', ['only' => ['index', 'show']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws JsonException
     */
    public function index(Request $request): JsonResponse
    {
        $connection_fees = ConnectionFee::with('user');
        $connection_fees = $this->filterQuery($request, $connection_fees)
            ->withSum('connection_fee_payments', 'amount_paid')
            ->withSum('connection_fee_payments', 'credit');

        $perPage = 10;
        if ($request->has('perPage')){
            $perPage = $request->perPage;
        }
        return response()->json($connection_fees->paginate($perPage));
    }

    /**
     * Display the specified resource.
     *
     * @param $ConnectionFee
     * @return JsonResponse
     */
    public function show($ConnectionFee): JsonResponse
    {
        $connection_fee = ConnectionFee::with('user.meter', 'connection_fee_payments')
            ->where('id', $ConnectionFee)
            ->withSum('connection_fee_payments', 'amount_paid')
            ->withSum('connection_fee_payments', 'credit')
            ->firstOrFail();
        if ($connection_fee->user->should_pay_connection_fee){
            $connection_fee->user->connection_fee_balance = $this->getUserConnectionFeeBalance($connection_fee->user);
        }
        return response()->json($connection_fee);
    }

    public function filterQuery(Request $request, $connection_fee)
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');
        $userId = $request->query('user_id');
        $fromDate = $request->query('fromDate');
        $toDate = $request->query('toDate');
        $status = $request->query('status');
        $month = $request->query('month');

        if ($request->has('station_id')) {
            $connection_fee = $connection_fee->select('connection_fees.*')
                ->join('users', 'users.id', 'connection_fees.user_id')
                ->join('meters', 'meters.id', 'users.meter_id')
                ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
                ->where('meter_stations.id', $stationId);
        }

        if ($request->has('user_id')) {
            $connection_fee = $connection_fee->select('connection_fees.*')
                ->join('users', 'users.id', 'connection_fees.user_id')
                ->where('users.id', $userId);
        }

        if (($request->has('fromDate') && Str::length($request->query('fromDate')) > 0) && ($request->has('toDate') && Str::length($request->query('toDate')) > 0)) {
            $formattedFromDate = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
            $formattedToDate = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
            $connection_fee = $connection_fee->whereBetween('connection_fees.created_at', [$formattedFromDate, $formattedToDate]);
        }

        if ($request->has('year') && !empty($request->query('year')) && $request->query('year') !== 'undefined') {
            $connection_fee = $connection_fee->whereYear('month', $request->query('year'));

        }

        if ($request->has('month') && !empty($request->query('month')) && $request->query('month') !== 'undefined') {
            $formattedFromDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
            $connection_fee = $connection_fee->where('month', $formattedFromDate);

        }

        if ($request->has('status')) {
            $decoded_status = json_decode($status, false, 512, JSON_THROW_ON_ERROR);
            if (!empty($decoded_status)){
                $connection_fee = $connection_fee->whereIn('status', $decoded_status);
            }

        }

//        if ($request->has('sortBy')) {
//            $connection_fee = $connection_fee->orderBy($sortBy, $sortOrder);
//        }

        return $connection_fee;
    }
}
