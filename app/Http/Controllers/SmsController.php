<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\SendSms;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    use SendSms;

    /**
     * @throws Exception
     */
    public function send(Request $request): JsonResponse
    {
        $users = User::role('user');
        if ($request->recipient !== 'all') {
            $station_id = $request->recipient;
            $users->whereHas('meter', function ($query) use ($station_id) {
                $query->where('station_id', $station_id);
            });
        }

        $users = $users->pluck('phone')
            ->all();
        $phone_numbers_string = implode(',', $users);

        if (empty($phone_numbers_string)) {
            $response = ['message' => 'The given data was invalid.', 'errors' => ['recipient' => ['Recipients not found']]];
            return response()->json($response, 422);
        }
        if ($this->initiateSendSms($phone_numbers_string, $request->message)) {
            return response()->json('sent');
        }
        return response()->json(['message' => 'Failed to send message, if error persists please contact website admin']);

    }
}
