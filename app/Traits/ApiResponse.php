<?php

// app/Traits/ApiResponse.php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function successResponse(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    protected function errorResponse(string $message, array|string|null $errors = null, int $status = 500, string $errorType = 'ServerError'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => [
                'type' => $errorType,
                'details' => $errors,
            ],
        ], $status);
    }
}

