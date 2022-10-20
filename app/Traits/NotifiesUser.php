<?php

namespace App\Traits;

use App\Enums\CommunicationChannels;
use App\Jobs\SendSMS;
use App\Mail\GeneralMessage;
use App\Mail\MeterReadings;
use App\Mail\MeterTokens;
use Mail;

trait NotifiesUser
{
    public function notifyUser($info, $user, $message_type, $phone_number = null): void
    {
        if ($this->shouldNotifyViaSms($user->communication_channels)) {
            $user_phone_number =
                ($phone_number && $this->isValidPhoneNumber($phone_number)) ?
                    $phone_number :
                    $user->phone;
            SendSMS::dispatch($user_phone_number, $info->message, $user->id);
        }

        if (($message_type === 'meter readings') && $this->shouldNotifyViaEmail($user->communication_channels)) {
            $message = $this->formatMessageForEmail($info);
            Mail::to($user->email)
                ->queue(new MeterReadings($info->bill_month, $message));
            return;
        }

        if (($message_type === 'meter tokens') && $this->shouldNotifyViaEmail($user->communication_channels)) {
            $message = $this->formatMessageForEmail($info);
            Mail::to($user->email)
                ->queue(new MeterTokens($message));
            return;
        }

        if (($message_type === 'general') && $this->shouldNotifyViaEmail($user->communication_channels)) {
            $message = $this->formatMessageForEmail($info);
            Mail::to($user->email)
                ->queue(new GeneralMessage($info->title, $message));
        }

    }

    private function isValidPhoneNumber($phone_number): bool
    {
        return preg_match('/^\s*(?:\+?(\d{1,3}))?[-. (]*(\d{3})[-. )]*(\d{3})[-. ]*(\d{4})(?: *x(\d+))?\s*$/', $phone_number);
    }

    public function shouldNotifyViaSms($user_communication_channels): bool
    {
        return in_array(CommunicationChannels::SMS, $user_communication_channels, false);
    }

    public function shouldNotifyViaEmail($user_communication_channels): bool
    {
        return in_array(CommunicationChannels::EMAIL, $user_communication_channels, false);
    }

    /**
     * @param $info
     * @return mixed
     */
    private function formatMessageForEmail($info)
    {
        return str_replace("\n", '<br/>', $info->message);
    }

}
