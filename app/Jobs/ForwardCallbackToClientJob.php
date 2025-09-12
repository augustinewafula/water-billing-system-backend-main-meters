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
    public $backoff = [3, 10, 30]; // 3 seconds, 10 seconds, 30 seconds

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
            $response = $this->deliverCallback($clientCallback, $formattedPayload);

            // Store response for debugging
            $this->storeCallbackResponse($response, $formattedPayload);

            // Mark context as client notified
            $this->context->update(['status' => 'client_notified']);

        } catch (\Exception $e) {
            $this->handleCallbackFailure($clientCallback, $formattedPayload, $e);
        }
    }

    private function formatCallbackForClient(array $callbackData, ClientRequestContext $context): array
    {
        $statusMapping = $this->mapHexingStatusToClient($callbackData);

        return [
            'success' => $statusMapping['success'],
            'message' => $statusMapping['message'],
            'data' => [
                'event_type' => 'valve_status_update',
                'meter_number' => $context->original_request['meter_number'] ?? null,
                'requested_action' => $context->action_type,
                'valve_status' => $statusMapping['valve_status'] ?? null,
                'timestamp' => $callbackData['dateTime'] ?? now()->toISOString(),
                'message_id' => $callbackData['messageId'],
            ],
            'errors' => $statusMapping['success'] ? null : [
                'type' => 'CallbackError',
                'details' => $statusMapping['message']
            ]
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

    private function deliverCallback(ClientCallbackUrl $callback, array $payload)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Hydro-Pro-Webhook/1.0'
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

        return $response;
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

    private function storeCallbackResponse($response, array $payload): void
    {
        $responseData = [
            'status_code' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->body(),
            'response_time' => $response->transferStats->getTransferTime() ?? null,
            'sent_payload' => $payload,
            'timestamp' => now()->toISOString(),
            'attempt' => $this->attempts()
        ];

        // Update context with response data
        $this->context->update([
            'callback_response' => $responseData
        ]);

        Log::info('Callback response stored for debugging', [
            'context_id' => $this->context->id,
            'message_id' => $this->context->message_id,
            'client_id' => $this->context->client_id,
            'status_code' => $response->status(),
            'response_size' => strlen($response->body()),
            'attempt' => $this->attempts()
        ]);
    }

    private function handleCallbackFailure(ClientCallbackUrl $callback, array $payload, \Exception $e): void
    {
        Log::error('Client callback delivery failed', [
            'client_id' => $callback->client_id,
            'callback_url' => $callback->callback_url,
            'message_id' => $payload['data']['message_id'] ?? null,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries
        ]);

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

        // Only increment callback retry count when job permanently fails
        $clientCallback = ClientCallbackUrl::where('client_id', $this->context->client_id)
            ->where('is_active', true)
            ->first();

        if ($clientCallback) {
            $clientCallback->increment('retry_count');
        }
    }
}
