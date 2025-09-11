<?php

namespace App\Http\Controllers\ExternalApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalRequests\RegisterCallbackRequest;
use App\Http\Requests\ExternalRequests\UpdateCallbackRequest;
use App\Models\ClientCallbackUrl;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    use ApiResponse;

    /**
     * Register your callback URL
     *
     * @group Callbacks
     * @authenticated
     * @bodyParam callback_url string required The HTTPS callback URL. Example: https://client.example.com/webhooks/meter-updates
     * @bodyParam secret_token string optional Secret token for webhook signature verification. Min 32 chars. Example: your-webhook-secret-token-min-32-chars
     */
    public function store(RegisterCallbackRequest $request): JsonResponse
    {
        \Log::info('Register callback request: ' . json_encode($request->all()));
        $clientId = $this->getClientId($request);

        $clientCallbackUrl = ClientCallbackUrl::create([
            'client_id' => $clientId,
            'callback_url' => $request->callback_url,
            'secret_token' => $request->secret_token,
            'max_retries' => 3,
            'timeout_seconds' => 30,
            'is_active' => true,
            'retry_count' => 0
        ]);

        return $this->successResponse('Callback URL registered successfully', [
            'callback_url' => $clientCallbackUrl->callback_url,
            'secret_token' => $clientCallbackUrl->secret_token,
        ]);
    }

    /**
     * Update existing callback URL configuration
     *
     * @group Callbacks
     * @authenticated
     * @bodyParam callback_url string optional The HTTPS callback URL. Example: https://client.example.com/webhooks/meter-updates
     * @bodyParam secret_token string optional Secret token for webhook signature verification. Min 32 chars. Example: your-webhook-secret-token-min-32-chars
     *
     * @response 200 scenario="Callback URL updated successfully" {
     *   "success": true,
     *   "message": "Callback URL updated successfully",
     *   "data": {
     *     "callback_url": "https://client.example.com/webhooks/meter-updates",
     *     "secret_token": "your-webhook-secret-token-min-32-chars"
     *   },
     *   "errors": null
     * }
     *
     * @response 404 scenario="Callback URL not found" {
     *   "success": false,
     *   "message": "No callback URL found for this client. Please register first.",
     *   "data": null,
     *   "errors": {
     *     "type": "CallbackNotFound",
     *     "details": null
     *   }
     * }
     * @throws AuthenticationException
     */
    public function update(UpdateCallbackRequest $request): JsonResponse
    {
        $clientId = $this->getClientId($request);

        $clientCallbackUrl = ClientCallbackUrl::where('client_id', $clientId)->first();

        if (!$clientCallbackUrl) {
            return $this->errorResponse(
                'No callback URL found for this client. Please register first.',
                null,
                404,
                'CallbackNotFound'
            );
        }

        $clientCallbackUrl->update([
            'callback_url' => $request->callback_url ?? $clientCallbackUrl->callback_url,
            'secret_token' => $request->filled('secret_token') ? $request->secret_token : $clientCallbackUrl->secret_token,
        ]);

        return $this->successResponse('Callback URL updated successfully', [
            'callback_url' => $clientCallbackUrl->callback_url,
            'secret_token' => $clientCallbackUrl->secret_token,
        ]);
    }

    /**
     * Get current callback URL configuration
     *
     * @group Callbacks
     * @authenticated
     *
     * @response 200 scenario="Current callback configuration" {
     *   "success": true,
     *   "message": "Current callback configuration",
     *   "data": {
     *     "callback_url": "https://client.example.com/webhooks/meter-updates",
     *     "secret_token": "your-webhook-secret-token-min-32-chars",
     *     "registered_at": "2025-09-11T10:30:45.000000Z",
     *     "last_updated": "2025-09-11T14:22:15.000000Z"
     *   },
     *   "errors": null
     * }
     *
     * @response 404 scenario="Callback URL not found" {
     *   "success": false,
     *   "message": "No callback URL found for this client",
     *   "data": null,
     *   "errors": {
     *     "type": "CallbackNotFound",
     *     "details": null
     *   }
     * }
     */
    public function show(Request $request): JsonResponse
    {
        $clientId = $this->getClientId($request);

        $clientCallbackUrl = ClientCallbackUrl::where('client_id', $clientId)->first();

        if (!$clientCallbackUrl) {
            return $this->errorResponse(
                'No callback URL found for this client',
                null,
                404,
                'CallbackNotFound'
            );
        }

        return $this->successResponse('Current callback configuration', [
            'callback_url' => $clientCallbackUrl->callback_url,
            'secret_token' => $clientCallbackUrl->secret_token,
            'registered_at' => $clientCallbackUrl->created_at,
            'last_updated' => $clientCallbackUrl->updated_at
        ]);
    }

    /**
     * Delete callback URL registration
     *
     * @group Callbacks
     * @authenticated
     *
     * @response 200 scenario="Callback URL deleted successfully" {
     *   "success": true,
     *   "message": "Callback URL deleted successfully",
     *   "data": null,
     *   "errors": null
     * }
     *
     * @response 404 scenario="Callback URL not found" {
     *   "success": false,
     *   "message": "No callback URL found for this client",
     *   "data": null,
     *   "errors": {
     *     "type": "CallbackNotFound",
     *     "details": null
     *   }
     * }
     */
    public function destroy(Request $request): JsonResponse
    {
        $clientId = $this->getClientId($request);

        $deleted = ClientCallbackUrl::where('client_id', $clientId)->delete();

        if (!$deleted) {
            return $this->errorResponse(
                'No callback URL found for this client',
                null,
                404,
                'CallbackNotFound'
            );
        }

        return $this->successResponse('Callback URL deleted successfully', null);
    }

    /**
     * Get the client ID from authenticated user context
     *
     * In production, this extracts the client ID from the authenticated user's context
     * via Laravel Passport authentication. Each authenticated user represents a client.
     *
     * @param Request $request
     * @return string The authenticated user's UUID serving as client identifier
     */
    private function getClientId(Request $request): string
    {
        // Get authenticated user via Laravel Passport
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required');
        }

        // Use the authenticated user's UUID as the client identifier
        // This ensures each authenticated user/client has their own callback configuration
        return $user->id;
    }
}
