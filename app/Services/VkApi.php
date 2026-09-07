<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VkApi
{
    public const VERSION = '5.199';

    public function __construct(private string $token)
    {
    }

    public function call(string $method, array $params = []): array
    {
        $response = Http::asForm()
            ->timeout(20)
            ->post("https://api.vk.com/method/{$method}", $params + [
                'access_token' => $this->token,
                'v' => self::VERSION,
                'lang' => 'ru',
            ])
            ->json();

        if (isset($response['error'])) {
            throw new VkApiException(
                $response['error']['error_msg'] ?? 'Неизвестная ошибка VK API',
                $response['error']['error_code'] ?? 0
            );
        }

        return $response['response'] ?? [];
    }

    // принимает id, короткое имя или ссылку на сообщество
    public function group(string $ref): array
    {
        $ref = trim($ref);
        $ref = preg_replace('~^https?://(m\.)?vk\.(com|ru)/~i', '', $ref);
        $ref = preg_replace('~[/?#].*$~', '', $ref);
        $ref = ltrim($ref, '-@');
        if (str_starts_with($ref, 'public')) {
            $ref = substr($ref, 6) ?: $ref;
        }
        if (str_starts_with($ref, 'club') && ctype_digit(substr($ref, 4))) {
            $ref = substr($ref, 4);
        }

        // groups.getById принимает и числовой id, и короткое имя;
        // utils.resolveScreenName оставлен как запасной путь (токенам VK ID он недоступен)
        try {
            return $this->fetchGroup($ref);
        } catch (VkApiException $e) {
            if (ctype_digit($ref)) {
                throw $e;
            }

            $resolved = $this->call('utils.resolveScreenName', ['screen_name' => $ref]);
            if (empty($resolved['object_id']) || !in_array($resolved['type'] ?? '', ['group', 'page', 'event'])) {
                throw new VkApiException("Сообщество «{$ref}» не найдено");
            }

            return $this->fetchGroup((string) $resolved['object_id']);
        }
    }

    private function fetchGroup(string $ref): array
    {
        $r = $this->call('groups.getById', ['group_id' => $ref, 'fields' => 'members_count']);
        $group = $r['groups'][0] ?? $r[0] ?? null;

        if (!$group || empty($group['id'])) {
            throw new VkApiException("Сообщество «{$ref}» не найдено");
        }

        return $group;
    }

    // дневная статистика сообщества за период
    public function stats(int $groupId, int $from, int $to): array
    {
        return $this->call('stats.get', [
            'group_id' => $groupId,
            'timestamp_from' => $from,
            'timestamp_to' => $to,
            'interval' => 'day',
        ]);
    }

    // посты со стены сообщества, попадающие в период
    public function wallPosts(int $groupId, int $from, int $to): array
    {
        $posts = [];

        for ($offset = 0; $offset < 600; $offset += 100) {
            $r = $this->call('wall.get', ['owner_id' => -$groupId, 'count' => 100, 'offset' => $offset]);
            $items = $r['items'] ?? [];
            if (!$items) {
                break;
            }

            foreach ($items as $p) {
                if ($p['date'] >= $from && $p['date'] <= $to) {
                    $posts[] = $p;
                }
            }

            // лента в обратном хронологическом порядке (кроме закреплённого поста) —
            // дальше листать незачем, когда дошли до постов старше периода
            $oldest = collect($items)->filter(fn ($p) => empty($p['is_pinned']))->min('date');
            if ($oldest !== null && $oldest < $from) {
                break;
            }
        }

        return $posts;
    }
}
