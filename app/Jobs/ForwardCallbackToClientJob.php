<?php

namespace App\Jobs;

use App\Models\ClientCallbackUrl;
use App\Models\ClientRequestContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForwardCallbackToClientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes

    public function __construct(
        private ClientRequestContext $context,
        private array $callbackData
    ) {}

    public function handle(): void
    {
        $clientCallback = ClientCallbackUrl::where('client_id', $this->context->client_id)
            ->where('is_active', true)
            ->first();

        if (!$clientCallback) {
            Log::warning('No active callback URL for client', [
                'client_id' => $this->context->client_id,
                'message_id' => $this->context->message_id
            ]);
            return;
        }

        $formattedPayload = $this->formatCallbackForClient($this->callbackData, $this->context);

        try {
            $this->deliverCallback($clientCallback, $formattedPayload);
            
            // Mark context as client notified
            $this->context->update(['status' => 'client_notified']);
            
        } catch (\Exception $e) {
            $this->handleCallbackFailure($clientCallback, $formattedPayload, $e);
        }
    }

    private function formatCallbackForClient(array $callbackData, ClientRequestContext $context): array
    {
        return [
            'event_type' => 'valve_status_update',
            'meter_number' => $context->original_request['meter_number'] ?? null,
            'requested_action' => $context->action_type,
            'status' => $this->mapHexingStatusToClient($callbackData),
            'timestamp' => $callbackData['dateTime'] ?? now()->toISOString(),
            'message_id' => $callbackData['messageId'],
            'raw_data' => $callbackData // For debugging/advanced clients
        ];
    }

    private function mapHexingStatusToClient(array $callbackData): array
    {
        $valveStatus = $callbackData['valve'] ?? null;
        $status = $callbackData['status'] ?? null;

        // For valve control callbacks
        if ($valveStatus !== null) {
            return match($valveStatus) {
                '128' => [
                    'success' => true,
                    'valve_status' => 'open',
                    'message' => 'Valve opened successfully'
                ],
                '129' => [
                    'success' => true,
                    'valve_status' => 'closed', 
                    'message' => 'Valve closed successfully'
                ],
                '400' => [
                    'success' => false,
                    'valve_status' => 'unknown',
                    'message' => 'Operation timed out'
                ],
                default => [
                    'success' => false,
                    'valve_status' => 'unknown',
                    'message' => 'Unknown status: ' . $valveStatus
                ]
            };
        }

        // For meter reading callbacks
        if ($status !== null) {
            return [
                'success' => $status === '0',
                'message' => $status === '0' ? 'Operation completed successfully' : 'Operation failed'
            ];
        }

        return [
            'success' => false,
            'message' => 'Unknown callback format'
        ];
    }

    private function deliverCallback(ClientCallbackUrl $callback, array $payload): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Water-Billing-System-Webhook/1.0'
        ];

        if ($callback->secret_token) {
            $headers['X-Webhook-Signature'] = $this->generateSignature($payload, $callback->secret_token);
        }

        $response = Http::timeout($callback->timeout_seconds)
            ->withHeaders($headers)
            ->post($callback->callback_url, $payload);

        $this->logCallbackDelivery($callback, $payload, $response);

        if (!$response->successful()) {
            throw new \Exception("Callback delivery failed with status: " . $response->status());
        }
    }

    private function generateSignature(array $payload, string $secretToken): string
    {
        $payloadString = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return 'sha256=' . hash_hmac('sha256', $payloadString, $secretToken);
    }

    private function logCallbackDelivery(ClientCallbackUrl $callback, array $payload, $response): void
    {
        Log::info('Client callback delivery attempt', [
            'client_id' => $callback->client_id,
            'callback_url' => $callback->callback_url,
            'message_id' => $payload['message_id'] ?? null,
            'response_status' => $response->status(),
            'response_body' => $response->body(),
            'attempt' => $this->attempts(),
            'payload_size' => strlen(json_encode($payload))
        ]);
    }

    private function handleCallbackFailure(ClientCallbackUrl $callback, array $payload, \Exception $e): void
    {
        $callback->increment('retry_count');

        Log::error('Client callback delivery failed', [
            'client_id' => $callback->client_id,
            'callback_url' => $callback->callback_url,
            'message_id' => $payload['message_id'] ?? null,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries
        ]);

        // If we've exceeded max retries for this callback URL, disable it
        if ($callback->retry_count >= $callback->max_retries) {
            $callback->update(['is_active' => false]);
            
            Log::warning('Client callback URL disabled due to repeated failures', [
                'client_id' => $callback->client_id,
                'callback_url' => $callback->callback_url,
                'total_failures' => $callback->retry_count
            ]);
        }

        // Re-throw to trigger job retry
        throw $e;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ForwardCallbackToClientJob permanently failed', [
            'context_id' => $this->context->id,
            'client_id' => $this->context->client_id,
            'message_id' => $this->context->message_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Mark context as failed
        $this->context->update(['status' => 'failed']);
    }
}
