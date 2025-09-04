<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class AiService
{
    public function chat(User $user, string $message, array $history = [], array $context = []): string
    {
        $url = rtrim(config('services.ai.url', env('AI_SERVICE_URL', 'http://ai:8000')), '/').'/chat';

        $payload = [
            'user_id' => $user->id,
            'org_id' => $user->organization_id,
            'message' => $message,
            'history' => $history,
            'context' => $context,
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ];

        $resp = Http::timeout(60)->post($url, $payload);
        if (!$resp->successful()) {
            return 'AI service is unavailable right now.';
        }

        $data = $resp->json();
        return (string) Arr::get($data, 'answer', 'No answer.');
    }
}

