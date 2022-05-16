<?php

namespace App\Jobs;

use App\Models\ConnectionFeeCharge;
use App\Models\User;
use App\Traits\GeneratesMonthlyConnectionFee;
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

    public $tries = 1;

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
            ->with('meter')
            ->whereShouldPayConnectionFee(true)
            ->get();
        foreach ($users as $user) {
            $connection_fee_charges = ConnectionFeeCharge::where('station_id', $user->meter->station_id)
                ->first();
            $connection_fee = $connection_fee_charges->connection_fee;
            $monthly_connection_fee = $connection_fee_charges->connection_fee_monthly_installment;
            if ($user->total_connection_fee_paid >= $connection_fee){
                continue;
            }
            $this->generateUserMonthlyConnectionFee($user, $monthly_connection_fee);
        }
    }

}
