<?php

namespace App\Services\Max;

use App\Models\MaxChat;
use App\Models\Setting;

// Список каналов, куда добавлен бот MAX. Готового метода «список чатов бота» в API больше нет,
// поэтому собираем идентификаторы из событий бота (добавили/удалили, новые посты) и держим у себя.
class MaxChats
{
    public static function configured(): bool
    {
        return (bool) Setting::get('max_bot_token');
    }

    public static function api(): MaxApi
    {
        $token = Setting::get('max_bot_token');
        if (!$token) {
            throw new MaxException('Не задан ключ MAX-бота — заполните его на странице «Настройки»');
        }

        return new MaxApi($token);
    }

    // забирает новые события, дополняет список каналов и обновляет число подписчиков
    public static function sync(): array
    {
        $api = self::api();
        $marker = Setting::get('max_updates_marker');
        $marker = $marker !== null ? (int) $marker : null;

        $ids = [];
        $removed = [];
        for ($page = 0; $page < 10; $page++) {
            $r = $api->updates($marker, ['bot_added', 'bot_removed', 'message_created', 'chat_title_changed']);
            foreach ($r['updates'] ?? [] as $u) {
                $type = $u['update_type'] ?? '';
                $chatId = $u['chat_id'] ?? ($u['message']['recipient']['chat_id'] ?? null);
                if (!$chatId) {
                    continue;
                }
                if ($type === 'bot_removed') {
                    $removed[(int) $chatId] = true;
                    unset($ids[(int) $chatId]);
                } else {
                    $ids[(int) $chatId] = true;
                    unset($removed[(int) $chatId]);
                }
            }
            $next = $r['marker'] ?? null;
            if ($next === null || $next === $marker || count($r['updates'] ?? []) === 0) {
                $marker = $next ?? $marker;
                break;
            }
            $marker = (int) $next;
        }
        if ($marker !== null) {
            Setting::set('max_updates_marker', (string) $marker);
        }

        foreach (array_keys($removed) as $chatId) {
            MaxChat::where('chat_id', $chatId)->update(['active' => false]);
        }

        // новые каналы и уже известные: подтягиваем название, ссылку и число подписчиков
        $known = MaxChat::where('active', true)->pluck('chat_id')->all();
        foreach (array_unique(array_merge(array_keys($ids), $known)) as $chatId) {
            try {
                $chat = $api->chat((int) $chatId);
            } catch (MaxException $e) {
                // бота выгнали или канал удалён — прячем из списка
                if (in_array($e->getCode(), [403, 404], true)) {
                    MaxChat::where('chat_id', $chatId)->update(['active' => false]);
                }
                continue;
            }
            MaxChat::updateOrCreate(['chat_id' => (int) $chatId], [
                'title' => $chat['title'] ?? null,
                'link' => $chat['link'] ?? null,
                'is_channel' => ($chat['type'] ?? '') === 'channel',
                'participants_count' => (int) ($chat['participants_count'] ?? 0),
                'active' => ($chat['status'] ?? 'active') === 'active',
            ]);
        }

        return MaxChat::where('active', true)->orderBy('title')->get()->all();
    }
}
