<?php

namespace App\Console\Commands;

use App\Enums\PrepaidMeterType;
use App\Models\Meter;
use App\Services\HexingMeterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetHexingMeterReadings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hexing:readings {--batch-size=10 : Number of meters to process in each batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieve real-time readings from all Hexing meters';

    protected HexingMeterService $hexingService;

    public function __construct(HexingMeterService $hexingService)
    {
        parent::__construct();
        $this->hexingService = $hexingService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting Hexing meter readings retrieval...');

        $hexingMeters = Meter::where('prepaid_meter_type', PrepaidMeterType::HEXING)
            ->whereNotNull('number')
            ->get();

        if ($hexingMeters->isEmpty()) {
            $this->info('No Hexing meters found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$hexingMeters->count()} Hexing meters");

        $batchSize = (int) $this->option('batch-size');
        $meterNumbers = $hexingMeters->pluck('number')->toArray();
        $batches = array_chunk($meterNumbers, $batchSize);

        $totalBatches = count($batches);
        $this->info("Processing {$totalBatches} batches of up to {$batchSize} meters each");

        $successfulBatches = 0;
        $failedBatches = 0;

        foreach ($batches as $index => $batch) {
            $batchNumber = $index + 1;
            $this->info("Processing batch {$batchNumber}/{$totalBatches} ({" . count($batch) . "} meters)");

            try {
                $response = $this->hexingService->getRealTimeReading($batch);

                Log::info("Hexing readings batch {$batchNumber} processed", [
                    'batch_number' => $batchNumber,
                    'meter_count' => count($batch),
                    'response' => $response
                ]);

                $successfulBatches++;
                $this->info("✓ Batch {$batchNumber} processed successfully");

            } catch (\Exception $e) {
                $failedBatches++;
                $this->error("✗ Batch {$batchNumber} failed: " . $e->getMessage());

                Log::error("Hexing readings batch {$batchNumber} failed", [
                    'batch_number' => $batchNumber,
                    'meter_count' => count($batch),
                    'error' => $e->getMessage(),
                    'meters' => $batch
                ]);
            }

            if ($batchNumber < $totalBatches) {
                $this->info('Waiting 2 seconds before next batch...');
                sleep(2);
            }
        }

        $this->info("Hexing meter readings retrieval completed!");
        $this->info("Successful batches: {$successfulBatches}");
        $this->info("Failed batches: {$failedBatches}");

        return $failedBatches === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
