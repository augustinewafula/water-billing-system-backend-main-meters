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
        $schedule->command('queue:work')->everyThreeMinutes()->withoutOverlapping(15);
        $schedule->command('queue:work')->everyFiveMinutes()->withoutOverlapping(20);
        $schedule->command('meters:switch-off-unpaid')->everyThreeMinutes()->withoutOverlapping();
        $schedule->command('meters:switch-on-paid')->everyTwoMinutes()->withoutOverlapping();
        $schedule->command('meters:confirm-valve-status')->everyThirtyMinutes()->withoutOverlapping();
        $schedule->command('meters:check-faulty')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('meters:send-disconnection-remainder')->everyFiveMinutes();
        $schedule->command('meters:get-last-communication-date')->everyFifteenMinutes();
        $schedule->command('meter-readings:send')->everyMinute();
        $schedule->command('meter-readings:get --type=daily')->daily();
        $schedule->command('meter-readings:get --type=monthly')->monthlyOn($this->meterReadingOn());
        $schedule->command('users:debit-connection-fee')->daily();
        $schedule->command('connection-fees:send-bill-remainder')->daily();
//        $schedule->command('monthly-service-charge:generate')->monthly();
        $schedule->command('backup:clean')->twiceDaily(0, 12);
        $schedule->command('backup:run --only-db')->twiceDaily();
        $schedule->command('passport:purge')->everyThirtyMinutes();
//        $schedule->command('prune:daily-meter-readings')->daily();
        $schedule->command('activitylog:clean --days=30')->daily();
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
