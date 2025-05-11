<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class InsufficientAssetsException extends Exception
{
    protected array $assets = [];

    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null, $assets = [])
    {
        parent::__construct($message, $code, $previous);
        $this->assets = $assets;
    }

    public function getAssets(): array
    {
        return $this->assets;
    }
}
