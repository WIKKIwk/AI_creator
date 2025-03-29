<?php

namespace App\Services\Cache;

interface Cache
{
    public function flush(): void;

    public function get($key): mixed;

    public function put($key, $value, $ttl = null): void;

    public function forget($key): void;
    public function has($key): bool;
}
