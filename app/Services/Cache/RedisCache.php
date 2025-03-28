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
        Redis::set($key, $value, $ttl);
    }

    public function has($key): bool
    {
        return Redis::exists($key);
    }
}
