<?php

namespace App\Traits;

use App\Models\MpesaTransaction;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Str;

trait NotifiesNewPayment
{

    use ConvertsPhoneNumberToInternationalFormat,
        SendsSms;

    /**
     * @throws Exception
     */
    public function notifyNewPayment(MpesaTransaction $mpesaTransaction): void
    {
        $payment_notification_phone_number = Setting::where('key', 'payment_notification_phone_number')
            ->value('value');

        if (Str::length($payment_notification_phone_number) >= 10) {
            $phone_number = $this->phoneNumberToInternationalFormat($payment_notification_phone_number);
            $name = Str::of("$mpesaTransaction->FirstName $mpesaTransaction->LastName")
                ->title()
                ->squish();
            $message = "New payment of Ksh {$mpesaTransaction->TransAmount} received from {$name} on {$mpesaTransaction->created_at->isoFormat('MMMM Do YYYY, h:mm a')}";
            $this->initiateSendSms($phone_number, $message, null);
        }

    }

}
