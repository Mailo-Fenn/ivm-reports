<?php

namespace App\Services\Max;

use Illuminate\Support\Facades\Http;

// Bot API мессенджера MAX (dev.max.ru): один бот агентства, ключ из настроек.
// Бот должен быть администратором канала клиента — тогда видны посты, просмотры и подписчики.
class MaxApi
{
    private const BASE = 'https://platform-api2.max.ru';

    public function __construct(private string $token)
    {
    }

    public function get(string $path, array $query = []): array
    {
        return $this->handle(Http::withHeaders(['Authorization' => $this->token])->timeout(30)->get(self::BASE.$path, $query));
    }

    public function post(string $path, array $query, array $body): array
    {
        return $this->handle(Http::withHeaders(['Authorization' => $this->token])->timeout(60)->withQueryParameters($query)->post(self::BASE.$path, $body));
    }

    private function handle($response): array
    {
        $json = $response->json() ?? [];
        if ($response->failed() || isset($json['code']) && isset($json['message']) && !isset($json['chat_id']) && !isset($json['updates'])) {
            $msg = $json['message'] ?? ('HTTP '.$response->status());
            throw new MaxException($msg, (int) $response->status(), (string) ($json['code'] ?? ''));
        }

        return $json;
    }

    public function me(): array
    {
        return $this->get('/me');
    }

    public function chat(int $chatId): array
    {
        return $this->get('/chats/'.$chatId);
    }

    // события бота с момента последнего маркера (без долгого ожидания)
    public function updates(?int $marker, array $types = []): array
    {
        $q = ['limit' => 1000, 'timeout' => 1];
        if ($marker !== null) {
            $q['marker'] = $marker;
        }
        if ($types) {
            $q['types'] = implode(',', $types);
        }

        return $this->get('/updates', $q);
    }

    // посты канала за интервал (unix time в секундах); API отдаёт от новых к старым по 100 штук
    public function messagesBetween(int $chatId, int $since, int $until): array
    {
        $out = [];
        $to = $until * 1000 + 999;
        for ($page = 0; $page < 30; $page++) {
            $r = $this->get('/messages', ['chat_id' => $chatId, 'from' => $since * 1000, 'to' => $to, 'count' => 100]);
            $items = $r['messages'] ?? [];
            if (!$items) {
                break;
            }
            $minTs = null;
            foreach ($items as $m) {
                $ts = (int) (($m['timestamp'] ?? 0) / 1000);
                if ($ts >= $since && $ts <= $until) {
                    $out[$m['body']['mid'] ?? spl_object_id((object) $m)] = $m;
                }
                $minTs = $minTs === null ? $m['timestamp'] : min($minTs, $m['timestamp']);
            }
            if (count($items) < 100 || $minTs === null) {
                break;
            }
            $to = (int) $minTs - 1;
        }

        return array_values($out);
    }

    // загрузка файла: /uploads → адрес → файл → payload для вложения сообщения
    public function uploadAttachment(string $type, string $absPath, string $name): array
    {
        $slot = $this->post('/uploads', ['type' => $type], []);
        $url = $slot['url'] ?? throw new MaxException('MAX не выдал адрес для загрузки файла');

        $up = Http::timeout(600)->attach('data', fopen($absPath, 'r'), $name)->post($url);
        $json = $up->json() ?? [];
        if ($up->failed()) {
            throw new MaxException('MAX не принял файл «'.$name.'»: HTTP '.$up->status());
        }

        // картинки возвращают карту photos, видео и файлы — token из ответа загрузки или из /uploads
        if ($type === 'image' && !empty($json['photos'])) {
            return ['type' => 'image', 'payload' => ['photos' => $json['photos']]];
        }
        $token = $json['token'] ?? $slot['token'] ?? null;
        if (!$token) {
            throw new MaxException('MAX не вернул идентификатор загруженного файла «'.$name.'»');
        }

        return ['type' => $type, 'payload' => ['token' => $token]];
    }

    // отправка в канал; свежезагруженное видео может быть ещё не обработано — ждём и повторяем
    public function sendMessage(int $chatId, string $text, array $attachments): array
    {
        $body = ['text' => $text, 'notify' => true];
        if ($attachments) {
            $body['attachments'] = $attachments;
        }
        $delay = 2;
        for ($try = 0; $try < 8; $try++) {
            try {
                return $this->post('/messages', ['chat_id' => $chatId], $body);
            } catch (MaxException $e) {
                if ($e->apiCode !== 'attachment.not.ready' && !str_contains($e->getMessage(), 'not.ready')) {
                    throw $e;
                }
                sleep($delay);
                $delay = min($delay * 2, 20);
            }
        }
        throw new MaxException('MAX не успел обработать вложение — попробуйте опубликовать ещё раз через минуту');
    }
}
