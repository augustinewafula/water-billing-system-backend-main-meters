<?php

namespace App\Jobs;

use App\Models\ConnectionFee;
use App\Models\Setting;
use App\Models\User;
use App\Traits\CalculatesUserAmount;
use App\Traits\NotifiesUser;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendConnectionFeeBillRemainder implements ShouldQueue
{
    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels,
        NotifiesUser,
        CalculatesUserAmount;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $send_connection_fee_bill_remainder_sms = Setting::where('key', 'send_connection_fee_bill_remainder_sms')
            ->value('value');
        $days_before_sending_connection_fee_bill_remainder_sms = Setting::where('key', 'days_before_sending_connection_fee_bill_remainder_sms')
            ->value('value');

        $remainder_date = Carbon::now()->addDays($days_before_sending_connection_fee_bill_remainder_sms);
        if ($send_connection_fee_bill_remainder_sms) {
            $connection_fees = ConnectionFee::whereBetween('month', [now(), $remainder_date])
                ->billRemainderSmsNotSent()
                ->where(static function ($query) {
                    $query->notPaid()
                        ->orWhere
                        ->hasBalance();
                })->get();
            \Log::info('Found ' . $connection_fees->count() . ' connection fees to send remainder sms.');
            foreach ($connection_fees as $connection_fee) {
                $this->sendBillRemainderSms($connection_fee->id);
            }
        }
    }

    private function sendBillRemainderSms($connection_fee_id): void
    {
        \Log::info('Sending bill remainder sms for connection fee id: ' . $connection_fee_id);
        $connection_fee = ConnectionFee::findOrfail($connection_fee_id);
        $user = User::findOrfail($connection_fee->user_id);

        $connection_fee_debt = $this->calculateUserConnectionFeeDebt($user->id);
        $bill_due_on = Carbon::createFromFormat('Y-m-d H:i:s', $connection_fee->month)
            ->toFormattedDateString();
        $connection_fee->update(['bill_remainder_sms_sent' => true]);

        if ($connection_fee_debt === 0) {
            return;
        }
        $message = "Hello {$user->name}, your connection fee debt of Ksh {$connection_fee_debt} is due on $bill_due_on. Please pay your bill on time.";
        $this->notifyUser((object)['message' => $message, 'title' => 'Connection fee debt.'], $user, 'general');


    }
}
