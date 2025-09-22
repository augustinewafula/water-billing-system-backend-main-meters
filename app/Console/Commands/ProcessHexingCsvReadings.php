<?php

namespace App\Console\Commands;

use App\Models\Meter;
use App\Models\DailyMeterReading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessHexingCsvReadings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hexing:process-csv {--file= : Specific CSV file to process} {--date= : Process CSV for specific date (YYYY-MM-DD)} {--silent : Run without output messages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process Hexing main meter readings from CSV files';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $silent = $this->option('silent');

        if (!$silent) {
            $this->info('Starting Hexing CSV readings processing...');
        }

        Log::info('Hexing CSV processing started');

        try {
            $csvFile = $this->getLatestCsvFile();

            if (!$csvFile) {
                $message = 'No CSV files found in /home/ftpuser/files';
                if (!$silent) {
                    $this->error($message);
                }
                Log::error('No CSV files found for processing');
                return Command::FAILURE;
            }

            if (!$silent) {
                $this->info("Processing file: {$csvFile}");
            }

            $processedCount = $this->processCsvFile($csvFile, $silent);

            if (!$silent) {
                $this->info("Successfully processed {$processedCount} meter readings");
            }

            Log::info('CSV processing completed', ['processed_count' => $processedCount, 'file' => basename($csvFile)]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            if (!$silent) {
                $this->error('Error processing CSV: ' . $e->getMessage());
            }
            Log::error('CSV processing failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    /**
     * Get the latest CSV file from FTP directory
     */
    private function getLatestCsvFile(): ?string
    {
        $ftpPath = '/home/ftpuser/files';

        if (!$this->option('silent')) {
            $this->info('Looking for CSV files in /home/ftpuser/files...');
        }

        if ($this->option('file')) {
            $file = $this->option('file');
            if (str_starts_with($file, '/')) {
                $fullPath = $file;
            } else {
                $fullPath = $ftpPath . '/' . $file;
            }

            if (file_exists($fullPath)) {
                return $fullPath;
            } else {
                if (!$this->option('silent')) {
                    $this->error('Specified file not found: ' . $file);
                }
                return null;
            }
        }

        if ($this->option('date')) {
            $date = Carbon::parse($this->option('date'))->format('Ymd');
            $files = glob($ftpPath . '/*.csv');

            foreach ($files as $file) {
                $filename = basename($file);
                if (str_contains($filename, $date)) {
                    return $file;
                }
            }

            if (!$this->option('silent')) {
                $this->error('No CSV file found for date: ' . $this->option('date'));
            }
            return null;
        }

        $files = glob($ftpPath . '/*.csv');

        if (empty($files)) {
            return null;
        }

        $latestFile = null;
        $latestTimestamp = 0;

        foreach ($files as $file) {
            $timestamp = filemtime($file);
            if ($timestamp > $latestTimestamp) {
                $latestTimestamp = $timestamp;
                $latestFile = $file;
            }
        }

        if (!$this->option('silent')) {
            $this->info('Found ' . count($files) . ' CSV files, using latest: ' . basename($latestFile));
        }

        return $latestFile;
    }

    /**
     * Process the CSV file and update meter readings
     */
    private function processCsvFile(string $csvFile, bool $silent = false): int
    {
        $content = file_get_contents($csvFile);
        $lines = str_getcsv($content, "\n");

        if (empty($lines)) {
            if (!$silent) {
                $this->error('CSV file is empty');
            }
            Log::error('CSV file is empty', ['file' => basename($csvFile)]);
            return 0;
        }

        $header = str_getcsv(array_shift($lines));

        $processedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        if (!$silent) {
            $this->info('Processing ' . count($lines) . ' data rows...');
        }

        foreach ($lines as $index => $line) {
            try {
                $data = str_getcsv($line);

                if (count($data) < count($header)) {
                    $skippedCount++;
                    continue;
                }

                $row = array_combine($header, $data);

                if (empty($row['meter_asset_no'])) {
                    $skippedCount++;
                    continue;
                }

                if ($this->processRowData($row, $index, $silent)) {
                    $processedCount++;
                } else {
                    $skippedCount++;
                }

            } catch (\Exception $e) {
                Log::error('Error processing CSV row', [
                    'row' => $index,
                    'error' => $e->getMessage()
                ]);
                $errorCount++;
            }
        }

        if (!$silent) {
            $this->info("Processing summary:");
            $this->info("- Processed: {$processedCount}");
            $this->info("- Skipped: {$skippedCount}");
            $this->info("- Errors: {$errorCount}");
        }

        if ($errorCount > 0) {
            Log::warning('CSV processing completed with errors', [
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'file' => basename($csvFile)
            ]);
        }

        return $processedCount;
    }

    /**
     * Process individual row data
     */
    private function processRowData(array $row, int $rowIndex, bool $silent = false): bool
    {
        $meterNumber = trim($row['meter_asset_no']);

        // First try exact match
        $meter = Meter::where('number', $meterNumber)->first();

        // If no exact match found, try with leading zeros
        if (!$meter) {
            // Remove leading zeros from CSV meter number for comparison
            $normalizedCsvNumber = ltrim($meterNumber, '0');

            // Find meters where the number without leading zeros matches
            $meter = Meter::whereRaw("LTRIM(number, '0') = ?", [$normalizedCsvNumber])->first();
        }

        if (!$meter) {
            return false;
        }

        $currentReading = $row['current_cumul_flow'] ?? $row['POSITIVE_CUMUL_FLOW_D'] ?? null;

        if ($currentReading === null || $currentReading === '') {
            return false;
        }

        $reading = (float) $currentReading;
        $readingDate = isset($row['tv']) ? Carbon::parse($row['tv']) : now();

        try {
            $this->updateMeterReading($meter, $reading, $readingDate, $row);

            if (!$silent) {
                $this->line("✓ Row {$rowIndex}: Updated meter {$meterNumber} with reading {$reading}");
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update meter reading', [
                'meter_number' => $meterNumber,
                'reading' => $reading,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update meter reading in database
     */
    private function updateMeterReading(Meter $meter, float $reading, Carbon $readingDate, array $rowData)
    {
        DailyMeterReading::create([
            'meter_id' => $meter->id,
            'reading' => $reading
        ]);

        $meter->update([
            'last_reading' => $reading,
            'last_reading_date' => $readingDate,
            'last_communication_date' => $readingDate,
            'battery_voltage' => $rowData['battery_voltage'] ?? $meter->battery_voltage,
            'valve_status' => $rowData['valve_status'] ?? $meter->valve_status
        ]);
    }
}
