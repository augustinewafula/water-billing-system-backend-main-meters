<?php

namespace App\Console\Commands;

use App\Http\Requests\MpesaPaymentRequest;
use App\Http\Requests\MpesaTransactionRequest;
use App\Models\MpesaPayment;
use App\Models\MpesaTransaction;
use App\Models\MpesaTransactionPullLog;
use App\Services\MpesaService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PullMpesaTransactions extends Command
{
    protected $signature = 'mpesa:pull-transactions {--start-date= : The start date for pulling transactions (format: Y-m-d H:i:s)}';
    protected $description = 'Pull transactions from M-Pesa and reconcile payments';

    public function __construct(private MpesaService $mpesaService)
    {
        parent::__construct();
    }

    /**
     * @throws \JsonException
     */
    public function handle(): void
    {
        if (!config('features.mpesa_pull_transactions')) {
            $this->info('This feature is disabled.');
            return;
        }

        // Retrieve the start date from the option or fallback to the last pull log or 6 hours ago
        $startDateOption = $this->option('start-date');
        $startDate = $startDateOption
            ? Carbon::createFromFormat('Y-m-d H:i:s', $startDateOption)
            : $this->getDefaultStartDate();

        $endDate = now();

        // Ensure startDate does not exceed the 48-hour restriction
        if ($startDate->lt(now()->subHours(48))) {
            $startDate = now()->subHours(48);
        }

        $startDateFormatted = $startDate->format('Y-m-d H:i:s');
        $endDateFormatted = $endDate->format('Y-m-d H:i:s');

        $this->logInfo("Pulling transactions from {$startDateFormatted} to {$endDateFormatted}");

        $response = $this->mpesaService->pullTransactions($startDateFormatted, $endDateFormatted);

        if (!isset($response['ResponseCode'])) {
            $this->logError("Invalid response received from the M-Pesa API.");
            return;
        }

        $this->processResponse($response, $endDate);
    }

    private function getDefaultStartDate(): Carbon
    {
        $lastPullLog = MpesaTransactionPullLog::latest('last_pulled_at')->first();

        if ($lastPullLog) {
            return Carbon::parse($lastPullLog->last_pulled_at)->subMinute(); // Subtract 1 minute for safe overlap
        }

        return now()->subHours(6);
    }


    /**
     * Process the response from the M-Pesa API.
     *
     * @param array $response
     * @param Carbon $endDate
     * @return void
     */
    private function processResponse(array $response, Carbon $endDate): void
    {
        $responseCode = $response['ResponseCode'];

        switch ($responseCode) {
            case '1000': // Success
                $transactions = $response['Response'] ?? [];
                $flattenedTransactions = $this->flattenTransactions($transactions);

                $transactionCount = count($flattenedTransactions);
                $this->logInfo("Total transactions pulled: {$transactionCount}");

                if ($transactionCount > 0) {
                    $transactionIds = array_column($flattenedTransactions, 'transactionId');
                    $this->logInfo("Transaction IDs: " . implode(', ', $transactionIds));
                }

                $missingTransactions = $this->reconcileTransactions($flattenedTransactions);
                $this->logInfo("Total missing transactions: " . count($missingTransactions));

                $this->handleMissingTransactions($missingTransactions);
                break;

            case '1001': // Null, no transactions
                $this->logInfo("No transactions available for the selected time period.");
                break;

            case '500': // Failure
                $this->logError("Failed to retrieve transactions. Please check the short code configuration.");
                break;

            default:
                $this->logError("Unexpected response code received: {$responseCode}");
        }

        if (in_array($responseCode, ['1000', '1001'], true)) {
            MpesaTransactionPullLog::create(['last_pulled_at' => $endDate]);
            $this->logInfo('Transaction pull completed successfully.');
        }
    }


    /**
     * Handles missing transactions by calling the acceptPayment method.
     *
     * @param array $missingTransactions
     * @return void
     */
    private function handleMissingTransactions(array $missingTransactions): void
    {
        $shortCode = config('services.mpesa.shortcode');
        foreach ($missingTransactions as $transaction) {
            try {
                // Create an MpesaPaymentRequest object
                $paymentRequest = new MpesaTransactionRequest([
                    'TransactionType' => $transaction['transactiontype'] ?? null,
                    'TransID' => $transaction['transactionId'],
                    'TransTime' => isset($transaction['trxDate']) ? Carbon::parse($transaction['trxDate'])->format('YmdHis') : null,
                    'TransAmount' => $transaction['amount'] ?? null,
                    'BusinessShortCode' => $shortCode,
                    'BillRefNumber' => $transaction['billreference'] ?? null,
                    'InvoiceNumber' => null,
                    'OrgAccountBalance' => null,
                    'ThirdPartyTransID' => null,
                    'MSISDN' => $transaction['msisdn'] ?? null,
                    'FirstName' => null,
                    'MiddleName' => null,
                    'LastName' => null,
                ]);

                // Call the acceptPayment method
                $this->mpesaService->acceptTransaction($paymentRequest);

                // Log the successful handling of the transaction
                $this->logInfo("Processed missing transaction: {$transaction['transactionId']}");
            } catch (\Exception $e) {
                // Log errors during processing
                $this->logError("Error processing missing transaction {$transaction['transactionId']}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Reconciles the pulled transactions with the MpesaPayment records.
     *
     * @param array $transactions
     * @return array List of missing transactions.
     */
    private function reconcileTransactions(array $transactions): array
    {
        $missingTransactions = [];

        foreach ($transactions as $transaction) {
            $exists = MpesaTransaction::where('TransID', $transaction['transactionId'])->exists();

            if (!$exists) {
                $missingTransactions[] = $transaction;
            }
        }

        return $missingTransactions;
    }

    /**
     * Flattens the nested structure of the transactions response.
     *
     * @param array $transactions
     * @return array Flattened list of transactions.
     */
    private function flattenTransactions(array $transactions): array
    {
        $flattened = [];

        foreach ($transactions as $batch) {
            foreach ($batch as $transaction) {
                $flattened[] = $transaction; // Add each transaction to the flat list
            }
        }

        return $flattened;
    }

    /**
     * Logs and displays informational messages.
     *
     * @param string $message
     * @return void
     */
    private function logInfo(string $message): void
    {
        $this->info($message);
        Log::info($message);
    }

    /**
     * Logs and displays error messages.
     *
     * @param string $message
     * @return void
     */
    private function logError(string $message): void
    {
        $this->error($message);
        Log::error($message);
    }
}
