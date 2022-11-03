<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendSmsRequest;
use App\Http\Requests\SmsCallbackRequest;
use App\Models\Sms;
use App\Models\User;
use App\Traits\SendsSms;
use Carbon\Carbon;
use Exception;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Log;
use Str;
use Throwable;

class SmsController extends Controller
{
    use SendsSms;

    public function __construct()
    {
        $this->middleware('permission:sms-list', ['only' => ['index']]);
        $this->middleware('permission:sms-create', ['only' => ['send', 'initiateSendSms']]);
    }

    /**
     * @throws JsonException
     */
    public function index(Request $request): JsonResponse
    {
        $sms = Sms::select('sms.*')->with('user:id,name,account_number');
        $sms = $this->filterQuery($request, $sms);
        $sms_cost = $sms->sum('cost');

        $perPage = 10;
        if ($request->has('perPage')){
            $perPage = $request->perPage;
        }
        $sms = $sms->paginate($perPage);
        return response()->json([
            'sms' => $sms,
            'sms_cost' => $sms_cost
        ]);
    }

    /**
     * @throws Exception
     */
    public function send(SendSmsRequest $request): JsonResponse
    {
        $users = User::role('user')
            ->with('meter');
        if ($request->recipient !== 'all' && $request->recipient !== 'specific') {
            $station_id = $request->recipient;
            $users->whereHas('meter', function ($query) use ($station_id) {
                $query->where('station_id', $station_id);
            });
        }
        if ($request->recipient === 'specific') {
            $recipients = $request->specific_recipients;
            $users->where('phone', $recipients[0]);
            foreach ($recipients as $key => $recipient) {
                $users = $users->orWhere('phone', $recipient);
            }
        }

        $users = $users->get();

        $total_users_count = $users->count();

        if ($total_users_count === 0) {
            $response = ['message' => 'The given data was invalid.', 'errors' => ['recipient' => ['Recipients not found']]];
            return response()->json($response, 422);
        }

        $failed_messages_count = 0;
        foreach ($users as $user) {
            try {
                $first_name = explode(' ', trim($user->name))[0];
                $to_replace = [$first_name, $user->name, $user->account_number, $user->meter->number, $user->unaccounted_debt];
                $personalized_message = $this->personalizeMessage($to_replace, $request->message);
                $this->initiateSendSms($user->phone, $personalized_message, $user->id, 'user');
            } catch (Throwable $th) {
                $failed_messages_count++;
                Log::info($th);
            }
        }

        if ($failed_messages_count === $total_users_count) {
            $response = ['message' => 'Failed to send message.'];
            return response()->json($response, 422);
        }
        if ($failed_messages_count > 0) {
            $response = ['message' => 'Some messages failed to send'];
            return response()->json($response, 422);
        }
        return response()->json('sent');
    }

    /**
     * @throws JsonException
     */
    public function getCreditBalance(): JsonResponse
    {
        $africas_talking_username = env('AFRICASTKNG_USERNAME');
        $africas_talking_username === 'sandbox' ?
            $url = "https://api.sandbox.africastalking.com/version1/user?username=$africas_talking_username" :
            $url = "https://api.africastalking.com/version1/user?username=$africas_talking_username";

        $response = Http::withHeaders([
            'apiKey' => env('AFRICASTKNG_APIKEY'),
            'Accept' => 'application/json'
        ])
            ->retry(2, 100)
            ->get($url);

        if ($response->successful()) {
            $response = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
            return response()->json(['balance' => $response->UserData->balance]);

        }

        $response = ['message' => 'Failed to get credit balance.'];
        return response()->json($response, 422);
    }

    public function callback(SmsCallbackRequest $request): JsonResponse
    {
        $sms = Sms::where('message_id', $request->id)->first();
        $status = $request->status;
        if ($status === 'Success'){
            $status = 'Delivered';
        }
        $sms->update([
            'status' => $status,
            'network_code' => $request->networkCode,
            'failure_reason' => $request->failureReason,
        ]);
        return response()->json('received');
    }

    private function personalizeMessage(array $replace_with, $message): string
    {
        $search_words = ['{first-name}', '{full-name}', '{account-number}', '{meter-number}', '{previous-balance}'];
        return str_replace($search_words, $replace_with, $message);
    }

    /**
     * @param Request $request
     * @param $sms
     * @return mixed
     * @throws JsonException
     */
    private function filterQuery(Request $request, $sms)
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');
        $fromDate = $request->query('fromDate');
        $toDate = $request->query('toDate');
        $status = $request->query('status');

        if ($request->has('search') && Str::length($search) > 0) {
            $sms = $sms->where(function ($sms) use ($search) {
                $sms->where('sms.phone', 'like', '%' . $search . '%')
                    ->orWhere('sms.status', 'like', '%' . $search . '%')
                    ->orWhere('sms.cost', 'like', '%' . $search . '%')
                    ->orWhere('sms.message', 'like', '%' . $search . '%')
                    ->orWhere('users.account_number', 'like', '%' . $search . '%')
                    ->orWhere('users.name', 'like', '%' . $search . '%');
            });
        }

        if (($request->has('fromDate') && Str::length($request->query('fromDate')) > 0) && ($request->has('toDate') && Str::length($request->query('toDate')) > 0)) {
            $formattedFromDate = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
            $formattedToDate = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
            $sms = $sms->whereBetween('sms.created_at', [$formattedFromDate, $formattedToDate]);
        }

        if ($status !== 'undefined') {
            $decoded_status = json_decode($status, false, 512, JSON_THROW_ON_ERROR);
            if (!empty($decoded_status)){
                $sms = $sms->whereIn('status', $decoded_status);
            }

        }

        if ($request->has('sortBy')) {
            $sms = $sms->orderBy($sortBy, $sortOrder);
        }

        if ($request->has('station_id')) {
            $sms = $sms->join('users', 'users.id', 'user_id')
                ->join('meters', 'meters.id', 'users.meter_id')
                ->join('meter_stations', 'meter_stations.id', 'meters.station_id')
                ->where('meter_stations.id', $stationId);
        }

        if ($request->has('user_id')) {
            $sms = $sms->whereHas('user', function ($query) use ($request) {
                $query->where('id', $request->query('user_id'));
            });
        }
        return $sms;
    }
}
