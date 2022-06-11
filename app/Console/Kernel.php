<?php

namespace App\Console;

use App\Models\Setting;
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
        $schedule->command('queue:restart')->everyFifteenMinutes();
        $schedule->command('queue:work')->everyThreeMinutes()->withoutOverlapping(60);
        $schedule->command('meters:switch-off-unpaid')->everyThreeMinutes()->withoutOverlapping();
        $schedule->command('meters:switch-on-paid')->everyTwoMinutes()->withoutOverlapping();
        $schedule->command('meters:confirm-valve-status')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('meters:check-faulty')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('meters:send-disconnection-remainder')->everyFiveMinutes();
        $schedule->command('meter-readings:send')->everyMinute();
        $schedule->command('meter-readings:get --type=daily')->daily();
        $schedule->command('meter-readings:get --type=monthly')->monthlyOn($this->meterReadingOn());
//        $schedule->command('monthly-service-charge:generate')->monthly();
        $schedule->command('monthly-connection-fee:generate')->monthly();
        $schedule->command('backup:clean')->twiceDaily(0, 12);
        $schedule->command('backup:run --only-db')->twiceDaily();
        $schedule->command('passport:purge')->everyThirtyMinutes();
    }

    public function meterReadingOn()
    {
        return Setting::where('key', 'meter_reading_on')
            ->value('value');
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
