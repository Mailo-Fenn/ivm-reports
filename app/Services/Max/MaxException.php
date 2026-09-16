<?php

namespace App\Services\Max;

class MaxException extends \RuntimeException
{
    public function __construct(string $message, int $status = 0, public readonly string $apiCode = '')
    {
        parent::__construct($message, $status);
    }
}
