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
        $schedule->command('queue:work --max-time=300')->everyThreeMinutes()->withoutOverlapping();
        $schedule->command('meter-readings:send')->everyThirtyMinutes();
        $schedule->command('meter-readings:get --type=daily')->dailyAt('23:50');
        $schedule->command('meter-readings:get --type=monthly')->lastDayOfMonth('23:50');
        $schedule->command('backup:clean --only-db')->twiceDaily(0, 12);
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
