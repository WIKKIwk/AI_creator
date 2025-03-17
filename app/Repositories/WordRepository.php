<?php

namespace App\Repositories;

use Illuminate\Http\JsonResponse;

class WordRepository
{
    public function getWords(): JsonResponse
    {
        return response()->json(['message' => 'getWords']);
    }

    public function getDictionaryWords(): JsonResponse
    {
        return response()->json(['message' => 'getDictionaryWords']);
    }

    public function store(): JsonResponse
    {
        return response()->json(['message' => 'store']);
    }

    public function show(): JsonResponse
    {
        return response()->json(['message' => 'show']);
    }

    public function update(): JsonResponse
    {
        return response()->json(['message' => 'update']);
    }

    public function delete(): JsonResponse
    {
        return response()->json(['message' => 'delete']);
    }

    public function move(): JsonResponse
    {
        return response()->json(['message' => 'move']);
    }

    public function progress(): JsonResponse
    {
        return response()->json(['message' => 'progress']);
    }
}
