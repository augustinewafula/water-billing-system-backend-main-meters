<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\User;
use App\Traits\GeneratesMonthlyConnectionFee;
use App\Traits\GeneratesMonthlyServiceCharge;
use App\Traits\NotifiesOnJobFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateMonthlyConnectionFee implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeneratesMonthlyConnectionFee, NotifiesOnJobFailure;

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
     * @throws Throwable
     */
    public function handle(): void
    {
        $users = User::role('user')
            ->get();
        $connection_fee = Setting::where('key', 'connection_fee')
            ->value('value');
        $monthly_connection_fee = Setting::where('key', 'connection_fee_per_month')
            ->value('value');
        foreach ($users as $user) {
            if ($user->total_connection_fee_paid === $connection_fee){
                continue;
            }
            $this->generateUserMonthlyConnectionFee($user, $monthly_connection_fee);
        }
    }

}
