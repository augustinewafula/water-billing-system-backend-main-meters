<?php

namespace App\Jobs;

use App\Enums\AlertContactTypes;
use App\Mail\Alert;
use App\Models\AlertContact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;

class SendAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->sendAlertEmail();
        $this->sendAlertSms();
    }

    private function sendAlertEmail(): void
    {
        $alert_contacts = AlertContact::where('type', AlertContactTypes::Email)
            ->get();
        foreach ($alert_contacts as $alert_contact){
            Mail::to($alert_contact->value)
                ->send(new Alert($this->message));

        }

    }

    private function sendAlertSms(): void
    {
        $alert_contacts = AlertContact::where('type', AlertContactTypes::Phone)
            ->get();
        foreach ($alert_contacts as $alert_contact){
            SendSMS::dispatch($alert_contact->value, $this->message, $alert_contact->user_id);

        }
    }
}
