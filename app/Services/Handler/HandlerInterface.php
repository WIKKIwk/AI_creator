<?php

namespace App\Services\Handler;

interface HandlerInterface
{
    public function handle(array $msg): void;
}
