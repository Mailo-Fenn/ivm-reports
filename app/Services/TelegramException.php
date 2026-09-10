<?php

namespace App\Services;

class TelegramException extends \RuntimeException
{
    // kind из ответа скрипта: config | unauthorized | ref | login | flood | rpc | error
    public function __construct(string $message, public readonly string $kind = 'error')
    {
        parent::__construct($message);
    }
}
