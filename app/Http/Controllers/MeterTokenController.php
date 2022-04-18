<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateMeterTokenRequest;
use App\Jobs\SendSMS;
use App\Models\MeterToken;
use App\Models\MpesaTransaction;
use App\Traits\ProcessesPrepaidMeterTransaction;
use Carbon\Carbon;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use Str;
use Throwable;

class MeterTokenController extends Controller
{
    use ProcessesPrepaidMeterTransaction;

    public function __construct()
    {
        $this->middleware('permission:meter-token-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:meter-token-edit', ['only' => ['update', 'resend']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
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
     * @throws Throwable
     */
    public function store(CreateMeterTokenRequest $request): JsonResponse
    {
        try {
            $mpesa_transaction = MpesaTransaction::where('TransID', $request->mpesa_transaction_reference)->first();
            $this->processPrepaidTransaction($request->meter_id, $mpesa_transaction, 0);
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error($throwable);
            $response = ['message' => 'Failed to generate token.'];
            return response()->json($response, 422);
        }
        return response()->json('generated');
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
     * Display the specified resource.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $meter_reading = MeterToken::with('meter.type', 'user', 'mpesa_transaction')
            ->where('id', $id)
            ->first();
        return response()->json($meter_reading);
    }

    public function resend($meterTokenId)
    {
        $user = MeterToken::select('users.id as user_id', 'users.account_number', 'users.phone', 'meters.id as meter_id', 'meters.number as meter_number', 'meter_tokens.token', 'meter_tokens.units', 'mpesa_transactions.TransID as transaction_id', 'mpesa_transactions.TransAmount as amount')
            ->join('meters', 'meters.id', 'meter_tokens.meter_id')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'meter_tokens.mpesa_transaction_id')
            ->join('users', 'meters.id', 'users.meter_id')
            ->where('meter_tokens.id', $meterTokenId)
            ->first();

        if (!$user) {
            $response = ['message' => 'Meter user not found, please contact website admin for help'];
            return response($response, 422);
        }

        $date = Carbon::now()->toDateTimeString();
        $message = "Meter: $user->meter_number\nToken: $user->token\nUnits: $user->units\nAmount: $user->amount\nDate: $date\nRef: $user->transaction_id";
        SendSMS::dispatch($user->phone, $message, $user->user_id);
        return response()->json('sent');
    }

}
