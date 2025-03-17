<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;

trait ApiHelper
{
    public function successRes(string $message = 'success', array $data = [], $statusCode = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public function errorRes(string $message = 'error', array $meta = [], $statusCode = 400): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'meta' => $meta,
        ], $statusCode);
    }

    public function user(): User
    {
        /** @var User $user */
        $user = auth('sanctum')->user();
        return $user;
    }
}
