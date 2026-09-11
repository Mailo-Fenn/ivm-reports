<?php

namespace App\Services\Publishing;

use App\Models\Post;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

// Публикация в канал Telegram через Bot API: бот агентства (ключ в настройках) должен быть
// администратором канала клиента с правом публикации. Канал берётся из проекта (telegram_channel).
class TelegramPublisher
{
    private const API = 'https://api.telegram.org/bot';
    // лимиты Bot API
    private const TEXT_LIMIT = 4096;
    private const CAPTION_LIMIT = 1024;

    public static function available(Post $post): bool
    {
        return (bool) (Setting::get('telegram_bot_token') && $post->project->telegram_channel);
    }

    // возвращает ['id' => message_id, 'url' => ссылка на пост или null]
    public function publish(Post $post): array
    {
        $token = Setting::get('telegram_bot_token');
        if (!$token) {
            throw new PublishException('Не задан ключ Telegram-бота — заполните его на странице «Настройки»');
        }
        $chat = self::chatId($post->project->telegram_channel);
        if ($chat === null) {
            throw new PublishException('У проекта не указан канал Telegram');
        }

        $text = trim((string) $post->text);
        $files = $post->mediaFiles();
        foreach ($files as $f) {
            if (!is_file($f['abs'])) {
                throw new PublishException("Файл «{$f['name']}» не найден на сервере");
            }
        }

        $base = self::API.$token.'/';

        if (!$files) {
            if ($text === '') {
                throw new PublishException('Пустая публикация: нет ни текста, ни файлов');
            }
            $msg = $this->call($base.'sendMessage', ['chat_id' => $chat, 'text' => mb_substr($text, 0, self::TEXT_LIMIT)]);

            return $this->result($msg, $chat);
        }

        // подпись к медиа ограничена 1024 символами — длинный текст уходит отдельным сообщением следом
        $caption = mb_strlen($text) <= self::CAPTION_LIMIT ? $text : '';

        if (count($files) === 1) {
            $f = $files[0];
            $method = $f['type'] === 'video' ? 'sendVideo' : 'sendPhoto';
            $field = $f['type'] === 'video' ? 'video' : 'photo';
            $msg = $this->call(
                $base.$method,
                array_filter(['chat_id' => $chat, 'caption' => $caption, 'supports_streaming' => $f['type'] === 'video' ? 'true' : null]),
                fn (PendingRequest $r) => $r->attach($field, fopen($f['abs'], 'r'), $f['name'])
            );
        } else {
            // альбом: до 10 файлов, подпись у первого элемента
            $media = [];
            $attach = fn (PendingRequest $r) => $r;
            foreach (array_slice($files, 0, 10) as $i => $f) {
                $item = ['type' => $f['type'] === 'video' ? 'video' : 'photo', 'media' => "attach://file{$i}"];
                if ($i === 0 && $caption !== '') {
                    $item['caption'] = $caption;
                }
                $media[] = $item;
                $prev = $attach;
                $attach = fn (PendingRequest $r) => $prev($r)->attach("file{$i}", fopen($f['abs'], 'r'), $f['name']);
            }
            $msgs = $this->call($base.'sendMediaGroup', ['chat_id' => $chat, 'media' => json_encode($media, JSON_UNESCAPED_UNICODE)], $attach);
            $msg = $msgs[0] ?? $msgs;
        }

        if ($caption === '' && $text !== '') {
            $this->call($base.'sendMessage', ['chat_id' => $chat, 'text' => mb_substr($text, 0, self::TEXT_LIMIT)]);
        }

        return $this->result($msg, $chat);
    }

    // проверка ключа бота — возвращает username бота
    public static function botUsername(string $token): string
    {
        $r = Http::timeout(20)->get(self::API.$token.'/getMe')->json();
        if (empty($r['ok'])) {
            throw new PublishException($r['description'] ?? 'Telegram не принял ключ бота');
        }

        return $r['result']['username'] ?? '';
    }

    // @имя канала → "@имя", числовой id → как есть, ссылка t.me/имя → "@имя"
    public static function chatId(?string $ref): ?string
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return null;
        }
        $ref = preg_replace('~^https?://(www\.)?(t\.me|telegram\.me)/~i', '', $ref);
        $ref = trim(explode('?', $ref)[0], '/');
        if (preg_match('~^-?\d+$~', $ref)) {
            return $ref;
        }

        return '@'.ltrim($ref, '@');
    }

    private function call(string $url, array $params, ?callable $prepare = null): array
    {
        $request = Http::timeout(120);
        if ($prepare) {
            $request = $prepare($request);
        }
        $response = $prepare ? $request->post($url, $params) : $request->asForm()->post($url, $params);
        $json = $response->json() ?? [];

        if (empty($json['ok'])) {
            $desc = $json['description'] ?? ('HTTP '.$response->status());
            throw new PublishException('Telegram: '.self::humanize($desc));
        }

        return $json['result'];
    }

    private function result(array $msg, string $chat): array
    {
        $id = (string) ($msg['message_id'] ?? '');
        $username = $msg['chat']['username'] ?? (str_starts_with($chat, '@') ? substr($chat, 1) : null);

        return ['id' => $id, 'url' => $username && $id ? "https://t.me/{$username}/{$id}" : null];
    }

    private static function humanize(string $desc): string
    {
        return match (true) {
            str_contains($desc, 'chat not found') => 'канал не найден — проверьте адрес канала в проекте',
            str_contains($desc, 'not enough rights'), str_contains($desc, 'have no rights') => 'у бота нет прав публиковать — добавьте его администратором канала',
            str_contains($desc, 'bot is not a member') => 'бот не добавлен в канал',
            str_contains($desc, 'Request Entity Too Large'), str_contains($desc, 'file is too big') => 'файл слишком большой для Bot API (до 50 МБ)',
            default => $desc,
        };
    }
}
