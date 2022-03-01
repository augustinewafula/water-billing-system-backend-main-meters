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

        if ($users->count() === 0) {
            $response = ['message' => 'The given data was invalid.', 'errors' => ['recipient' => ['Recipients not found']]];
            return response()->json($response, 422);
        }

        foreach ($users as $user) {
            $first_name = explode(' ', trim($user->name))[0];
            $to_replace = [$first_name, $user->name, $user->meter->number];
            $personalized_message = $this->personalizeMessage($to_replace, $request->message);
            $this->initiateSendSms($user->phone, $personalized_message);
        }
        return response()->json('sent');
    }

    private function personalizeMessage(array $replace_with, $message): string
    {
        $search_words = ['{first-name}', '{full-name}', '{meter-number}'];
        return str_replace($search_words, $replace_with, $message);
    }
}
