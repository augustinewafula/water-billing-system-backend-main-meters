<?php

namespace App\Console\Commands;

use App\Models\Meter;
use App\Services\HexingMeterService;
use Illuminate\Console\Command;

class TestHexingMeterService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hexing:test
                            {method : The method to test (control-valve|real-time-reading|send-token)}
                            {meter_number : The meter number to test}
                            {--valve-action= : Valve action for control-valve method (open|close|exit)}
                            {--tokens=* : Tokens array for send-token method}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test HexingMeterService methods (controlValve, getRealTimeReading, sendToken)';

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
        $method = $this->argument('method');
        $meterNumber = $this->argument('meter_number');

        // Validate meter exists in database
        $meter = Meter::where('number', $meterNumber)->first();
        if (!$meter) {
            $this->error("Meter with number '{$meterNumber}' not found in database.");
            return Command::FAILURE;
        }

        $this->info("Found meter: {$meter->number} (ID: {$meter->id})");

        try {
            switch ($method) {
                case 'control-valve':
                    return $this->testControlValve($meterNumber);

                case 'real-time-reading':
                    return $this->testRealTimeReading($meterNumber);

                case 'send-token':
                    return $this->testSendToken($meterNumber);

                default:
                    $this->error("Invalid method '{$method}'. Use: control-valve, real-time-reading, or send-token");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Error testing method '{$method}': " . $e->getMessage());
            $this->line("Stack trace: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * @throws \Exception
     */
    private function testControlValve(string $meterNumber): int
    {
        $valveAction = $this->option('valve-action');

        if (!$valveAction) {
            $valveAction = $this->choice(
                'Select valve action:',
                ['open', 'close', 'exit'],
                0
            );
        }

        if (!in_array($valveAction, ['open', 'close', 'exit'])) {
            $this->error("Invalid valve action '{$valveAction}'. Use: open, close, or exit");
            return Command::FAILURE;
        }

        $this->info("Testing controlValve method...");
        $this->info("Meter: {$meterNumber}");
        $this->info("Valve Action: {$valveAction}");

        // Test with single meter number (new functionality)
        $response = $this->hexingService->controlValve($meterNumber, $valveAction);

        $this->info("Response received:");
        $this->line(json_encode($response, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function testRealTimeReading(string $meterNumber): int
    {
        $this->info("Testing getRealTimeReading method...");
        $this->info("Meter: {$meterNumber}");

        // Test with single meter number (new functionality)
        $response = $this->hexingService->getRealTimeReading($meterNumber);

        $this->info("Response received:");
        $this->line(json_encode($response, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function testSendToken(string $meterNumber): int
    {
        $tokens = $this->option('tokens');

        if (empty($tokens)) {
            $this->info("No tokens provided via --tokens option. Please enter tokens:");
            $tokensInput = $this->ask('Enter tokens (comma separated)');

            if (empty($tokensInput)) {
                $this->error("No tokens provided.");
                return Command::FAILURE;
            }

            $tokens = array_map('trim', explode(',', $tokensInput));
        }

        $this->info("Testing sendToken method...");
        $this->info("Meter: {$meterNumber}");
        $this->info("Tokens: " . implode(', ', $tokens));

        $response = $this->hexingService->sendToken($meterNumber, $tokens);

        $this->info("Response received:");
        $this->line(json_encode($response, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
