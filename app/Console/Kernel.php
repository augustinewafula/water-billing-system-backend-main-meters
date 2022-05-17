<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('queue:work --max-time=3600')->everyThreeMinutes()->withoutOverlapping();
        $schedule->command('meters:switch-off-unpaid')->everyMinute()->withoutOverlapping();
        $schedule->command('meters:confirm-valve-status')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('meters:check-faulty')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('meters:send-disconnection-remainder')->everyFiveMinutes();
        $schedule->command('meter-readings:send')->everyMinute();
        $schedule->command('meter-readings:get --type=daily')->daily();
        $schedule->command('model:prune')->daily();
        $schedule->command('meter-readings:get --type=monthly')->monthlyOn(25);
//        $schedule->command('monthly-service-charge:generate')->monthly();
        $schedule->command('monthly-connection-fee:generate')->monthly();
        $schedule->command('backup:clean')->twiceDaily(0, 12);
        $schedule->command('backup:run --only-db')->twiceDaily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
