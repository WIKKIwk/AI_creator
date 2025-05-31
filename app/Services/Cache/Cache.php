<?php

namespace App\Services\Cache;

interface Cache
{
    public function flush(): void;

    public function get($key): mixed;

    public function put($key, $value, $ttl = 3600): void;

    public function forget($key): void;
    public function has($key): bool;
}
