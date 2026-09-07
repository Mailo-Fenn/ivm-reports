<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class YouTubeApi
{
    private const DATA_URL = 'https://www.googleapis.com/youtube/v3/';
    private const ANALYTICS_URL = 'https://youtubeanalytics.googleapis.com/v2/reports';

    public function __construct(private string $token)
    {
    }

    // YouTube Data API v3 — данные каналов, плейлистов, видео
    public function data(string $resource, array $params = []): array
    {
        return $this->get(self::DATA_URL.$resource, $params);
    }

    private function get(string $url, array $params): array
    {
        $response = Http::withToken($this->token)->timeout(20)->get($url, $params);
        $json = $response->json() ?? [];

        if ($response->failed() || isset($json['error'])) {
            $err = $json['error'] ?? [];
            $reason = $err['errors'][0]['reason'] ?? ($err['status'] ?? '');
            $msg = $err['message'] ?? ('HTTP '.$response->status());
            throw new YouTubeApiException(trim($msg.($reason ? " ({$reason})" : '')), (int) ($err['code'] ?? $response->status()));
        }

        return $json;
    }

    // принимает id канала (UC…), @handle, имя пользователя или ссылку на канал
    public function channel(string $ref): array
    {
        $ref = trim($ref);
        $ref = preg_replace('~^(?:https?://)?(www\.|m\.)?youtube\.com/~i', '', $ref);
        $ref = preg_replace('~[?#].*$~', '', $ref);
        $ref = trim($ref, '/');

        $lookups = [];
        // хвосты вроде /videos или /about отбрасываем — важен только первый сегмент
        if (preg_match('~^(?:channel/)?(UC[\w-]{22})(?:/|$)~', $ref, $m)) {
            $lookups[] = ['id' => $m[1]];
        } elseif (preg_match('~^(?:c/|user/)?@?([^/]+)~', $ref, $m)) {
            // @handle встречается чаще всего; старые адреса /c/… и /user/… пробуем как handle и username
            $name = ltrim($m[1], '@');
            $lookups[] = ['forHandle' => '@'.$name];
            $lookups[] = ['forUsername' => $name];
        } else {
            throw new YouTubeApiException("Не удалось разобрать адрес канала «{$ref}»");
        }

        foreach ($lookups as $q) {
            $r = $this->data('channels', $q + ['part' => 'snippet,statistics,contentDetails', 'maxResults' => 1]);
            if (!empty($r['items'][0]['id'])) {
                $c = $r['items'][0];

                return [
                    'id' => $c['id'],
                    'title' => $c['snippet']['title'] ?? $c['id'],
                    'subscribers' => (int) ($c['statistics']['subscriberCount'] ?? 0),
                    'uploads' => $c['contentDetails']['relatedPlaylists']['uploads'] ?? null,
                ];
            }
        }

        throw new YouTubeApiException("Канал «{$ref}» не найден");
    }

    // видео канала, опубликованные в интервале [$start, $end] (unix time) — через плейлист загрузок,
    // это в 100 раз дешевле по квоте, чем search.list
    public function videosBetween(?string $uploadsPlaylist, int $start, int $end): array
    {
        if (!$uploadsPlaylist) {
            return [];
        }

        $out = [];
        $pageToken = null;
        // плейлист отсортирован по дате загрузки, а не публикации: отложенные премьеры
        // могут стоять «не по порядку», поэтому останавливаемся с запасом в 60 дней
        $stopBefore = $start - 60 * 86400;

        for ($page = 0; $page < 20; $page++) {
            $params = ['part' => 'contentDetails', 'playlistId' => $uploadsPlaylist, 'maxResults' => 50];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }
            $r = $this->data('playlistItems', $params);

            $reachedOld = false;
            foreach ($r['items'] ?? [] as $item) {
                $published = $item['contentDetails']['videoPublishedAt'] ?? null;
                if (!$published) {
                    continue;
                }
                $ts = strtotime($published);
                if ($ts >= $start && $ts <= $end) {
                    $out[] = ['id' => $item['contentDetails']['videoId'], 'published_at' => $ts];
                }
                if ($ts < $stopBefore) {
                    $reachedOld = true;
                }
            }

            $pageToken = $r['nextPageToken'] ?? null;
            if (!$pageToken || $reachedOld) {
                break;
            }
        }

        return $out;
    }

    // YouTube Analytics API — дневная статистика канала; даты в формате Y-m-d.
    // Доступна только владельцу или менеджеру канала.
    public function dailyStats(string $channelId, string $startDate, string $endDate): array
    {
        $r = $this->get(self::ANALYTICS_URL, [
            'ids' => 'channel=='.$channelId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'metrics' => 'views,likes,comments,shares,subscribersGained,subscribersLost',
            'dimensions' => 'day',
            'sort' => 'day',
        ]);

        $cols = array_map(fn ($h) => $h['name'], $r['columnHeaders'] ?? []);
        $rows = [];
        foreach ($r['rows'] ?? [] as $row) {
            $rows[] = array_combine($cols, $row);
        }

        return $rows;
    }
}
