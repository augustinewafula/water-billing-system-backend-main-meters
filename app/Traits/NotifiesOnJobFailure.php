<?php

namespace App\Traits;

use App\Mail\CriticalNotification;
use Mail;
use Throwable;

trait NotifiesOnJobFailure
{
    /**
     * Handle a job failure.
     *
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        $to = env('CRITICAL_NOTIFICATIONS_EMAIL');
        if ($to){
            Mail::to($to)
                ->queue(new CriticalNotification($exception));

        }
    }
}
