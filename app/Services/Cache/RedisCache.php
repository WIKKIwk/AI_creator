<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Redis;

class RedisCache implements Cache
{
    public function flush(): void
    {
        Redis::flushAll();
    }

    public function get($key): mixed
    {
        return Redis::get($key);
    }

    public function put($key, $value, $ttl = null): void
    {
        if ($ttl && $ttl > 0) {
            // Use EX seconds TTL explicitly for portability
            try {
                Redis::setex($key, (int)$ttl, $value);
            } catch (\Throwable $e) {
                // Fallback to option array if supported
                Redis::set($key, $value, ['ex' => (int)$ttl]);
            }
        } else {
            Redis::set($key, $value);
        }
    }

    public function forget($key): void
    {
        Redis::del($key);
    }

    public function has($key): bool
    {
        return Redis::exists($key);
    }
}
