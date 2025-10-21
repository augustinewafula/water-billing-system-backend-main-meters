<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class GulfAfricanBankWebhookController extends Controller
{
    /**
     * Handle incoming payment notification from Gulf African Bank
     *
     * Expected Payload:
     * - User: Username provided by 3rd party
     * - Password: Password provided by 3rd party
     * - TransactionDate: Transaction date and time (YYYYMMDDhhmmss)
     * - TransactionAmount: Transaction amount
     * - TransactionNarration: Narration quoted by customer
     * - TransactionReference: Transaction reference number
     * - TransactionDRCRIndicator: Debit/Credit indicator (D/C)
     * - Hash: SHA256 hash value for validation
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleNotification(Request $request): JsonResponse
    {
        try {
            // Log the incoming request
            Log::channel('daily')->info('GAB Webhook Notification Received', [
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
                'timestamp' => now()->toDateTimeString()
            ]);

            // Extract payload data
            $payload = $request->all();

            // Log structured transaction data
            Log::channel('daily')->info('GAB Transaction Details', [
                'user' => $payload['User'] ?? null,
                'transaction_date' => $payload['TransactionDate'] ?? null,
                'transaction_amount' => $payload['TransactionAmount'] ?? null,
                'transaction_narration' => $payload['TransactionNarration'] ?? null,
                'transaction_reference' => $payload['TransactionReference'] ?? null,
                'transaction_type' => $payload['TransactionDRCRIndicator'] ?? null,
                'hash' => $payload['Hash'] ?? null,
            ]);

            // TODO: Implement the following:
            // 1. Validate username and password
            // 2. Validate hash value using SECRET_KEY
            //    Hash = BASE64(SHA256(TransactionDate + TransactionAmount + TransactionNarration + TransactionReference + TransactionDRCRIndicator))
            // 3. Check for duplicate transactions using TransactionReference
            // 4. Process the transaction based on TransactionDRCRIndicator (C = Credit, D = Debit)
            // 5. Update billing records or account balances

            // For now, return success response
            return response()->json([
                'Result' => 'Success'
            ], 200);

        } catch (\Exception $e) {
            // Log the error
            Log::channel('daily')->error('GAB Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);

            // Return failure response
            return response()->json([
                'Result' => 'Failure'
            ], 500);
        }
    }

    /**
     * Validate the hash from Gulf African Bank
     *
     * @param array $payload
     * @param string $secretKey
     * @return bool
     */
    private function validateHash(array $payload, string $secretKey): bool
    {
        // Concatenate the required fields
        $dataToHash =
            ($payload['TransactionDate'] ?? '') .
            ($payload['TransactionAmount'] ?? '') .
            ($payload['TransactionNarration'] ?? '') .
            ($payload['TransactionReference'] ?? '') .
            ($payload['TransactionDRCRIndicator'] ?? '');

        // Add secret key to the data
        $dataToHash .= $secretKey;

        // Generate hash
        $hash = hash('sha256', $dataToHash);
        $base64Hash = base64_encode($hash);

        // Compare with provided hash
        return $base64Hash === ($payload['Hash'] ?? '');
    }
}
