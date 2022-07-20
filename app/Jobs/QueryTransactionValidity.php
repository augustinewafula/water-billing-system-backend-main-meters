<?php

namespace App\Jobs;

use App\Enums\UnverifiedMpesaTransactionStatus;
use App\Models\UnverifiedMpesaTransaction;
use App\Services\MpesaService;
use Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;
use Log;

class QueryTransactionValidity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $transaction_reference_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($transaction_reference_id)
    {
        $this->transaction_reference_id = $transaction_reference_id;
    }

    /**
     * Execute the job.
     *
     * @param MpesaService $mpesaService
     * @return void
     * @throws JsonException
     */
    public function handle(MpesaService $mpesaService): void
    {
        $data = [
            'Initiator' => env('MPESA_INITIATOR'),
            'SecurityCredential' => env('MPESA_SECURITY_CREDENTIAL'),
            'CommandID' => 'TransactionStatusQuery',
            'TransactionID' => $this->transaction_reference_id,
            'PartyA' => env('MPESA_SHORT_CODE'),
            'IdentifierType' => '1',
            'ResultURL' => 'https://backend.progressiveutilities.com/api/v1/query-transaction-status-callback',
            'QueueTimeOutURL' => 'https://backend.progressiveutilities.com/api/v1/query-transaction-status-queue-timeout-callback',
            'Remarks' => 'Confirming',
            'Occasion' => 'Ip mismatch'
        ];
        $response = Http::withToken($mpesaService->generateAccessToken())
            ->post('https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query', $data);
        Log::info('QueryTransactionValidity response'.$response->body());
        if ($response->successful()) {
            $response = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
            if ($response->ResponseCode === '0') {
                $unverified_mpesa_transaction = UnverifiedMpesaTransaction::where('TransID', $this->transaction_reference_id)->firstOrFail();
                $unverified_mpesa_transaction->Status = UnverifiedMpesaTransactionStatus::PENDING;
                $unverified_mpesa_transaction->ConversationalId = $response->ConversationID;
                $unverified_mpesa_transaction->Originator = $response->OriginatorConversationID;
                $unverified_mpesa_transaction->save();
            }

        }
    }
}
