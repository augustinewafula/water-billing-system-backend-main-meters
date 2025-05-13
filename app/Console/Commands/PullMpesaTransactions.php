<?php

namespace App\Console\Commands;

use App\Http\Requests\MpesaTransactionRequest;
use App\Models\MpesaTransaction;
use App\Models\MpesaTransactionPullLog;
use App\Models\PaybillCredential;
use App\Services\MpesaService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PullMpesaTransactions extends Command
{
    protected $signature = 'mpesa:pull-transactions {--start-date= : Optional start date (Y-m-d H:i:s)}';
    protected $description = 'Pull and reconcile M-Pesa transactions for all registered Paybill shortcodes';

    public function __construct(private MpesaService $mpesaService)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        if (!config('features.mpesa_pull_transactions')) {
            $this->info('M-Pesa pull feature is disabled.');
            return;
        }

        $paybills = PaybillCredential::all();

        if ($paybills->isEmpty()) {
            $this->error('No Paybill credentials configured.');
            return;
        }

        $customStartDate = $this->option('start-date');
        $startDateOption = $customStartDate
            ? Carbon::createFromFormat('Y-m-d H:i:s', $customStartDate)
            : null;

        foreach ($paybills as $paybill) {
            $shortcode = $paybill->shortcode;

            $startDate = $startDateOption ?? $this->getStartDateForShortcode($shortcode);
            $endDate = now();

            // Enforce 48-hour limit
            if ($startDate->lt(now()->subHours(48))) {
                $startDate = now()->subHours(48);
            }

            $startFormatted = $startDate->format('Y-m-d H:i:s');
            $endFormatted = $endDate->format('Y-m-d H:i:s');

            $this->logInfo("Pulling transactions for shortcode {$shortcode} from {$startFormatted} to {$endFormatted}");

            $response = $this->mpesaService->pullTransactions($shortcode, $startFormatted, $endFormatted);

            if (!isset($response['ResponseCode'])) {
                $this->logError("Invalid response for shortcode {$shortcode}.");
                continue;
            }

            $this->processResponse($shortcode, $response, $endDate);
        }
    }

    private function getStartDateForShortcode(string $shortcode): Carbon
    {
        $lastLog = MpesaTransactionPullLog::where('shortcode', $shortcode)
            ->latest('last_pulled_at')
            ->first();

        return $lastLog
            ? Carbon::parse($lastLog->last_pulled_at)->subMinute()
            : now()->subHours(6);
    }

    private function processResponse(string $shortcode, array $response, Carbon $endDate): void
    {
        $responseCode = $response['ResponseCode'];

        switch ($responseCode) {
            case '1000': // Success
                $transactions = $response['Response'] ?? [];
                $flattened = $this->flattenTransactions($transactions);

                $this->logInfo("Shortcode {$shortcode} - Pulled " . count($flattened) . " transactions.");

                $missing = $this->reconcileTransactions($flattened);
                $this->logInfo("Shortcode {$shortcode} - Missing transactions: " . count($missing));

                $this->handleMissingTransactions($shortcode, $missing);
                break;

            case '1001': // No transactions
                $this->logInfo("Shortcode {$shortcode} - No transactions in the time range.");
                break;

            case '500':
                $this->logError("Shortcode {$shortcode} - API failure. Check credentials.");
                break;

            default:
                $this->logError("Shortcode {$shortcode} - Unexpected response code: {$responseCode}");
        }

        if (in_array($responseCode, ['1000', '1001'], true)) {
            MpesaTransactionPullLog::updateOrCreate(
                ['shortcode' => $shortcode],
                ['last_pulled_at' => $endDate]
            );

            $this->logInfo("Shortcode {$shortcode} - Pull log updated.");
        }
    }

    private function reconcileTransactions(array $transactions): array
    {
        return collect($transactions)
            ->reject(fn($txn) => MpesaTransaction::where('TransID', $txn['transactionId'])->exists())
            ->values()
            ->all();
    }

    private function handleMissingTransactions(string $shortcode, array $transactions): void
    {
        foreach ($transactions as $txn) {
            try {
                $request = new MpesaTransactionRequest([
                    'TransactionType' => $txn['transactiontype'] ?? null,
                    'TransID' => $txn['transactionId'],
                    'TransTime' => isset($txn['trxDate']) ? Carbon::parse($txn['trxDate'])->format('YmdHis') : null,
                    'TransAmount' => $txn['amount'] ?? null,
                    'BusinessShortCode' => $shortcode,
                    'BillRefNumber' => $txn['billreference'] ?? null,
                    'InvoiceNumber' => null,
                    'OrgAccountBalance' => null,
                    'ThirdPartyTransID' => null,
                    'MSISDN' => $txn['msisdn'] ?? null,
                    'FirstName' => null,
                    'MiddleName' => null,
                    'LastName' => null,
                ]);

                $this->mpesaService->acceptTransaction($request);
                $this->logInfo("Shortcode {$shortcode} - Processed transaction: {$txn['transactionId']}");

            } catch (\Throwable $e) {
                $this->logError("Shortcode {$shortcode} - Failed processing {$txn['transactionId']}: {$e->getMessage()}");
            }
        }
    }

    private function flattenTransactions(array $transactions): array
    {
        return collect($transactions)
            ->flatMap(fn($batch) => $batch)
            ->values()
            ->all();
    }

    private function logInfo(string $message): void
    {
        $this->info($message);
        Log::info($message);
    }

    private function logError(string $message): void
    {
        $this->error($message);
        Log::error($message);
    }
}
