<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\User;
use App\Traits\GeneratesMonthlyServiceCharge;
use App\Traits\GeneratesPassword;
use App\Traits\NotifiesOnJobFailure;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMonthlyServiceCharge implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeneratesMonthlyServiceCharge, NotifiesOnJobFailure, GeneratesPassword;

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
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 600;

    /**
     * The unique ID of the job.
     *
     * @return string
     * @throws Exception
     */
    public function uniqueId(): string
    {
        return $this->generatePassword(5);
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Throwable
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
