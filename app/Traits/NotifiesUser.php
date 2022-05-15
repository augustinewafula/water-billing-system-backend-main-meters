<?php

namespace App\Traits;

use App\Enums\CommunicationChannels;
use App\Jobs\SendSMS;
use App\Mail\MeterReadings;
use App\Mail\MeterTokens;
use Mail;

trait NotifiesUser
{
    public function notifyUser($info, $user, $message_type): void
    {
        if ($this->shouldNotifyViaSms($user->communication_channels)){
            SendSMS::dispatch($user->phone, $info->message, $user->id);
        }

        if (($message_type === 'meter readings') && $this->shouldNotifyViaEmail($user->communication_channels)) {
            $message = str_replace("\n", '<br/>', $info->message);
            Mail::to($user->email)
                ->send(new MeterReadings($info->bill_month, $message));
        }

        if (($message_type === 'meter tokens') && $this->shouldNotifyViaEmail($user->communication_channels)) {
            $message = str_replace("\n", '<br/>', $info->message);
            Mail::to($user->email)
                ->send(new MeterTokens($message));
        }

    }

    public function shouldNotifyViaSms($user_communication_channels): bool
    {
        return in_array(CommunicationChannels::Sms, $user_communication_channels, false);
    }

    public function shouldNotifyViaEmail($user_communication_channels): bool
    {
        return in_array(CommunicationChannels::Email, $user_communication_channels, false);
    }

}
