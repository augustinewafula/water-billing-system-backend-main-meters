<?php

namespace App\Http\Controllers;

use App\Models\MeterToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Str;

class MeterTokenController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $meter_tokens = MeterToken::select('meter_tokens.id', 'meter_tokens.token', 'meter_tokens.units', 'meter_tokens.service_fee', 'meters.id as meter_id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount_paid', 'meters.number as meter_number', 'users.id as user_id', 'users.name as user_name', 'meter_tokens.created_at')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('users', 'users.meter_id', 'meters.id');
        $meter_tokens = $this->filterQuery($request, $meter_tokens);
        return response()->json($meter_tokens->paginate(10));
    }

    /**
     * @param Request $request
     * @param $meter_tokens
     * @return mixed
     */
    public function filterQuery(Request $request, $meter_tokens)
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');

        if ($request->has('search') && Str::length($request->query('search')) > 0) {
            $meter_tokens = $meter_tokens->where(function ($meter_tokens) use ($search) {
                $meter_tokens->whereHas('mpesa_transaction', function ($query) use ($search) {
                    $query->where('TransAmount', 'like', '%' . $search . '%');
                })->orWhere('meter_tokens.token', 'like', '%' . $search . '%')
                    ->orWhere('meters.number', 'like', '%' . $search . '%')
                    ->orWhere('users.name', 'like', '%' . $search . '%')
                    ->orWhere('meter_tokens.units', 'like', '%' . $search . '%');
            });
        }
        if ($request->has('station_id')) {
            $meter_tokens = $meter_tokens->join('meter_stations', 'meter_stations.id', 'meters.station_id')
                ->where('meter_stations.id', $stationId);
        }
        if ($request->has('meter_id')) {
            $meter_tokens = $meter_tokens->where('meters.id', $request->query('meter_id'));
        }
        if ($request->has('user_id')) {
            $meter_tokens = $meter_tokens->where('users.id', $request->query('user_id'));
        }
        if ($request->has('sortBy')) {
            $meter_tokens = $meter_tokens->orderBy($sortBy, $sortOrder);
        }
        return $meter_tokens;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
