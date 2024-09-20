<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneFailedJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prune:failed-jobs {days? : The number of days to retain failed jobs (default is 30)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete failed jobs older than a specified number of days';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $days = $this->argument('days') ?? '7';
        $days = (int) $days;

        // Calculate the cutoff date
        $cutoffDate = now()->subDays($days);

        // Delete failed jobs older than the cutoff date
        $deletedRows = DB::table('failed_jobs')
            ->where('failed_at', '<', $cutoffDate)
            ->delete();

        $this->info("Deleted $deletedRows failed jobs older than $days days.");

        return 0;
    }
}

