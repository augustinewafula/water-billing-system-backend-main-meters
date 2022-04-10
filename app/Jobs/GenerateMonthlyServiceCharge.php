<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\User;
use App\Traits\GeneratesMonthlyServiceCharge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMonthlyServiceCharge implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeneratesMonthlyServiceCharge;

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
        $users = User::role('user')
            ->get();
        $monthly_service_charge = Setting::where('key', 'monthly_service_charge')
            ->first()
            ->value;
        foreach ($users as $user) {
            $this->generateUserMonthlyServiceCharge($user, $monthly_service_charge);
        }
    }

}
