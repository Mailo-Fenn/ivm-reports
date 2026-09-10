<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Process;

// Обёртка над telegram/stats.py (Telethon): один аккаунт Telegram агентства,
// api_id/api_hash из настроек, файл сессии в storage/app/telegram
class TelegramStats
{
    public static function configured(): bool
    {
        return (bool) (Setting::get('telegram_api_id') && Setting::get('telegram_api_hash'));
    }

    public static function connected(): bool
    {
        return (bool) Setting::get('telegram_user');
    }

    public static function sessionPath(): string
    {
        $dir = storage_path('app/telegram');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir.'/agency';
    }

    // интерпретатор: виртуальное окружение проекта, иначе системный python
    public static function python(): string
    {
        foreach ([base_path('telegram/.venv/bin/python'), base_path('telegram/.venv/Scripts/python.exe')] as $venv) {
            if (file_exists($venv)) {
                return $venv;
            }
        }

        return config('portal.python') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
    }

    public function me(): array
    {
        return $this->run('me');
    }

    public function loginStart(string $phone): string
    {
        return $this->run('login_start', [$phone])['phone_code_hash'] ?? throw new TelegramException('Telegram не вернул код подтверждения');
    }

    // ['need_password' => true] либо ['user' => [...]]
    public function loginCode(string $phone, string $hash, string $code): array
    {
        return $this->run('login_code', [$phone, $hash, $code]);
    }

    public function loginPassword(string $password): array
    {
        return $this->run('login_password', [$password]);
    }

    public function logout(): void
    {
        try {
            $this->run('logout');
        } finally {
            @unlink(self::sessionPath().'.session');
        }
    }

    public function channel(string $ref): array
    {
        return $this->run('channel', [$ref]);
    }

    public function count(string $ref): int
    {
        return (int) ($this->run('count', [$ref])['subscribers'] ?? 0);
    }

    // ['channel' => [...], 'posts' => [...], 'followers_by_day' => [date => net]|null, 'stats_error' => ?string]
    public function stats(string $ref, int $since, int $until): array
    {
        return $this->run('stats', [$ref, (string) $since, (string) $until]);
    }

    public function run(string $command, array $args = []): array
    {
        if (!self::configured()) {
            throw new TelegramException('Не заданы api_id и api_hash Telegram — заполните их на странице «Настройки»', 'config');
        }

        // файл сессии Telethon (sqlite) не переживает параллельный доступ — сериализуем вызовы
        $lockFile = fopen(self::sessionPath().'.lock', 'c');
        if ($lockFile) {
            flock($lockFile, LOCK_EX);
        }

        try {
            $result = Process::path(base_path())
                ->env([
                    'TG_API_ID' => Setting::get('telegram_api_id'),
                    'TG_API_HASH' => Setting::get('telegram_api_hash'),
                    'TG_SESSION' => self::sessionPath(),
                    'PYTHONIOENCODING' => 'utf-8',
                ])
                ->timeout(150)
                ->run([self::python(), 'telegram/stats.py', $command, ...$args]);
        } finally {
            if ($lockFile) {
                flock($lockFile, LOCK_UN);
                fclose($lockFile);
            }
        }

        $json = json_decode(trim($result->output()), true);

        if (is_array($json) && isset($json['error'])) {
            $kind = $json['kind'] ?? 'error';
            if ($kind === 'unauthorized') {
                Setting::set('telegram_user', null);
            }
            throw new TelegramException($json['error'], $kind);
        }

        if (!$result->successful() || !is_array($json)) {
            $err = trim($result->errorOutput()) ?: trim($result->output());
            throw new TelegramException('Скрипт Telegram не ответил: '.mb_substr($err ?: 'пустой вывод', -300), 'error');
        }

        return $json;
    }
}
