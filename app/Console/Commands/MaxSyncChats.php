<?php

namespace App\Console\Commands;

use App\Services\Max\MaxChats;
use Illuminate\Console\Command;

// собирает события бота MAX и обновляет список каналов, куда он добавлен
class MaxSyncChats extends Command
{
    protected $signature = 'max:sync-chats';

    protected $description = 'Обновить список каналов MAX, где бот агентства — администратор';

    public function handle(): int
    {
        if (!MaxChats::configured()) {
            $this->info('Ключ MAX-бота не задан.');

            return self::SUCCESS;
        }

        try {
            $chats = MaxChats::sync();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($chats as $c) {
            $this->info("{$c->chat_id}: {$c->title} ({$c->participants_count})");
        }
        $this->info('Каналов: '.count($chats));

        return self::SUCCESS;
    }
}
