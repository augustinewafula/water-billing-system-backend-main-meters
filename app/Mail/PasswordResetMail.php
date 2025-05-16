<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $token;
    public string $email;

    /**
     * Create a new message instance.
     *
     * @param string $token
     * @param string $email
     * @return void
     */
    public function __construct(string $token, string $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->to($this->email)
            ->subject('Reset Password')
            ->view('emails.forgetPassword')
            ->with([
                'token' => $this->token,
                'email' => $this->email,
            ]);
    }
}
