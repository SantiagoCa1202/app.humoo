<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    public static function success(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(
        Request $request,
        string $message,
        string $code,
        int $status,
        array $errors = []
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'errors' => (object) $errors,
            'request_id' => $request->attributes->get('request_id'),
        ], $status);
    }
}
