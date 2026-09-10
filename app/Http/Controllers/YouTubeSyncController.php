<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\GoogleOAuth;
use App\Services\SubscriberTotals;
use App\Services\YouTubeApi;
use App\Services\YouTubeApiException;
use Carbon\Carbon;

class YouTubeSyncController extends Controller
{
    // недели отчёта считаем по московским суткам, как и для ВК
    private const TZ = 'Europe/Moscow';

    public function sync(Report $report)
    {
        $token = GoogleOAuth::validAccessToken();
        if (!$token) {
            return back()->with('error', GoogleOAuth::connected()
                ? 'Токен Google не удалось обновить — переподключите Google на странице «Настройки»'
                : 'Google не подключён — авторизуйтесь на странице «Настройки»');
        }

        if (!$report->project->youtube_channel) {
            return back()->with('error', 'У проекта не указан канал YouTube — заполните поле в настройках проекта');
        }

        $yt = new YouTubeApi($token);

        $start = Carbon::create($report->year, $report->month, 1, 0, 0, 0, self::TZ);
        $end = $start->copy()->endOfMonth();

        try {
            $channel = $yt->channel($report->project->youtube_channel);
            $videos = $yt->videosBetween($channel['uploads'], $start->timestamp, $end->timestamp);
        } catch (YouTubeApiException $e) {
            return back()->with('error', 'Ошибка YouTube API: '.$e->getMessage());
        } catch (\Throwable) {
            return back()->with('error', 'Не удалось связаться с YouTube API — проверьте соединение');
        }

        // Analytics отдаёт данные только владельцу или менеджеру канала (403 — доступа нет);
        // в этом случае подтягиваем только количество видео, не трогая остальные цифры
        $statsAvailable = true;
        $days = [];
        try {
            // до сегодня, а не до конца месяца: по приросту после месяца восстанавливаем число подписчиков на его конец
            $days = $yt->dailyStats($channel['id'], $start->toDateString(), Carbon::now(self::TZ)->max($end)->toDateString());
        } catch (YouTubeApiException $e) {
            // 403 приходит и когда API не включён в Google Cloud (accessNotConfigured) — это не про права на канал
            if ($e->getCode() !== 403 || str_contains($e->getMessage(), 'accessNotConfigured')) {
                return back()->with('error', 'Ошибка YouTube Analytics: '.$e->getMessage());
            }
            $statsAvailable = false;
        } catch (\Throwable) {
            return back()->with('error', 'Не удалось связаться с YouTube API — проверьте соединение');
        }

        // недели отчёта: дни 1–7, 8–14, 15–21, 22 — конец месяца
        $weekOfDay = fn (int $day) => min(4, intdiv($day - 1, 7) + 1);

        $weeks = array_fill_keys(range(1, 4), ['views' => 0, 'inter' => 0, 'posts' => 0]);

        $netByDay = [];
        foreach ($days as $d) {
            $netByDay[$d['day']] = (int) ($d['subscribersGained'] ?? 0) - (int) ($d['subscribersLost'] ?? 0);
            if ($d['day'] > $end->toDateString()) {
                continue;
            }
            $i = $weekOfDay((int) substr($d['day'], 8, 2));
            $weeks[$i]['views'] += (int) ($d['views'] ?? 0);
            $weeks[$i]['inter'] += (int) ($d['likes'] ?? 0) + (int) ($d['comments'] ?? 0) + (int) ($d['shares'] ?? 0);
        }

        // подписчики — общее число на конец каждой недели (сейчас минус прирост после неё)
        $subsByWeek = $statsAvailable
            ? SubscriberTotals::byWeek((int) ($channel['subscribers'] ?? 0), $netByDay, $start, $end)
            : null;

        foreach ($videos as $v) {
            $weeks[$weekOfDay((int) Carbon::createFromTimestamp($v['published_at'], self::TZ)->day)]['posts']++;
        }

        // охваты (показы) API YouTube не отдаёт, заявки и сторис к YouTube не относятся — эти поля не трогаем
        foreach ($weeks as $i => $w) {
            if ($subsByWeek !== null && $subsByWeek[$i] !== null) {
                $w['subs'] = $subsByWeek[$i];
            }
            $report->weeklyStats()->updateOrCreate(
                ['platform' => 'yt', 'position' => $i],
                ($statsAvailable ? $w : ['posts' => $w['posts']]) + ['label' => "Неделя $i"]
            );
        }

        $report->syncPlatformStats();

        if (!$statsAvailable) {
            return back()->with('success', "Видео канала «{$channel['title']}» за {$report->period_label} подтянуты. Статистика (просмотры, подписчики, взаимодействия) недоступна: подключённый аккаунт Google не является владельцем или менеджером этого канала.");
        }

        return back()->with('success', "Данные YouTube подтянуты: «{$channel['title']}», {$report->period_label}");
    }
}
