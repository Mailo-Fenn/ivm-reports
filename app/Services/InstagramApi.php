<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

// Instagram API with Instagram Login (Meta): работает от имени бизнес-/авторского аккаунта,
// токен которого выдан проекту. Все запросы идут через /me — аккаунт определяется токеном.
class InstagramApi
{
    public const VERSION = 'v23.0';
    private const BASE = 'https://graph.instagram.com/';

    public function __construct(private string $token)
    {
    }

    public function get(string $path, array $params = []): array
    {
        $response = Http::timeout(20)->get(self::BASE.self::VERSION.'/'.ltrim($path, '/'), $params + ['access_token' => $this->token]);
        $json = $response->json() ?? [];

        if ($response->failed() || isset($json['error'])) {
            $err = $json['error'] ?? [];
            $msg = $err['message'] ?? ('HTTP '.$response->status());
            throw new InstagramApiException($msg, (int) ($err['code'] ?? $response->status()));
        }

        return $json;
    }

    // профиль аккаунта, которому выдан токен
    public function me(): array
    {
        return $this->get('me', ['fields' => 'user_id,username,name,followers_count,media_count']);
    }

    // суммарные метрики аккаунта за интервал [$since, $until] (unix time):
    // reach — уникальный охват, views — просмотры, total_interactions — лайки+комментарии+сохранения+репосты
    public function totals(int $since, int $until): array
    {
        $r = $this->get('me/insights', [
            'metric' => 'reach,views,total_interactions',
            'period' => 'day',
            'metric_type' => 'total_value',
            'since' => $since,
            'until' => $until,
        ]);

        $out = [];
        foreach ($r['data'] ?? [] as $m) {
            $out[$m['name']] = (int) ($m['total_value']['value'] ?? 0);
        }

        return $out;
    }

    // новые подписчики по дням; Meta отдаёт их только за последние 30 дней
    public function newFollowersByDay(int $since, int $until): array
    {
        $r = $this->get('me/insights', [
            'metric' => 'follower_count',
            'period' => 'day',
            'since' => $since,
            'until' => $until,
        ]);

        $out = [];
        foreach ($r['data'][0]['values'] ?? [] as $v) {
            $out[substr($v['end_time'], 0, 10)] = (int) ($v['value'] ?? 0);
        }

        return $out;
    }

    // публикации (посты, карусели, Reels) за интервал; сторис сюда не входят
    public function mediaBetween(int $since, int $until): array
    {
        $out = [];
        $params = ['fields' => 'id,timestamp,media_product_type', 'since' => $since, 'until' => $until, 'limit' => 100];
        $path = 'me/media';

        for ($page = 0; $page < 20; $page++) {
            $r = $this->get($path, $params);
            $reachedOld = false;
            foreach ($r['data'] ?? [] as $m) {
                $ts = strtotime($m['timestamp'] ?? '') ?: 0;
                if ($ts >= $since && $ts <= $until) {
                    $out[] = ['id' => $m['id'], 'published_at' => $ts, 'type' => $m['media_product_type'] ?? ''];
                }
                if ($ts && $ts < $since) {
                    $reachedOld = true;
                }
            }
            // следующая страница приходит готовым URL с курсором
            $next = $r['paging']['next'] ?? null;
            if (!$next || $reachedOld) {
                break;
            }
            $after = $r['paging']['cursors']['after'] ?? null;
            if (!$after) {
                break;
            }
            $params['after'] = $after;
        }

        return $out;
    }

    // активные сторис (последние 24 часа)
    public function activeStories(): array
    {
        $r = $this->get('me/stories', ['fields' => 'id,timestamp', 'limit' => 100]);

        return array_map(fn ($s) => ['id' => $s['id'], 'published_at' => strtotime($s['timestamp'] ?? '') ?: time()], $r['data'] ?? []);
    }
}
