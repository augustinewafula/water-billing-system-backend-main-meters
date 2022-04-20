<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendSmsRequest;
use App\Models\Sms;
use App\Models\User;
use App\Traits\SendsSms;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request): JsonResponse
    {
        $sms = Sms::select('sms.*');
        $search = $request->query('search');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');

        if ($request->has('search') && Str::length($search) > 0) {
            $sms = $sms->where(function ($sms) use ($search) {
                $sms->where('sms.phone', 'like', '%' . $search . '%')
                    ->orWhere('sms.status', 'like', '%' . $search . '%')
                    ->orWhere('sms.cost', 'like', '%' . $search . '%')
                    ->orWhere('sms.message', 'like', '%' . $search . '%');
            });
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

        return response()->json($sms->paginate(10));
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
                $to_replace = [$first_name, $user->name, $user->account_number, $user->meter->number];
                $personalized_message = $this->personalizeMessage($to_replace, $request->message);
                $this->initiateSendSms($user->phone, $personalized_message, $user->id);
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

    private function personalizeMessage(array $replace_with, $message): string
    {
        $search_words = ['{first-name}', '{full-name}', '{account-number}', '{meter-number}'];
        return str_replace($search_words, $replace_with, $message);
    }
}
