<?php

namespace App\Services\Max;

use App\Models\ChannelSubscriberSnapshot;
use App\Models\Report;
use Carbon\Carbon;

/**
 * Сбор статистики канала MAX в отчёт. Один и тот же код используют кнопка
 * «Подтянуть из MAX» в отчёте и команда по расписанию (max:sync-stats).
 *
 * Что отдаёт MAX Bot API: просмотры и репосты поста, комментарии к посту,
 * число подписчиков канала. Реакций в Bot API нет — ни поля, ни метода,
 * поэтому во «взаимодействия» идут репосты + пересылки + комментарии.
 * Охваты и заявки MAX не отдаёт — эти поля не трогаем.
 */
class MaxReportSync
{
    public const TZ = 'Europe/Moscow';

    /** Считать ли комментарии (по запросу на каждый пост — чуть дольше, но точнее) */
    public bool $withComments = true;

    /**
     * @return array{title:string, posts:int, views:int, inter:int, subs:?int, noSnapshots:bool}
     *
     * @throws MaxException
     */
    public function run(Report $report): array
    {
        $project = $report->project;

        $start = Carbon::create($report->year, $report->month, 1, 0, 0, 0, self::TZ);
        $end = $start->copy()->endOfMonth();

        $api = MaxChats::api();
        $chatId = (int) $project->max_channel_id;
        $chat = $api->chat($chatId);
        $posts = $api->messagesBetween($chatId, $start->timestamp, $end->timestamp);

        $weekOf = fn (int $ts) => min(4, intdiv(Carbon::createFromTimestamp($ts, self::TZ)->day - 1, 7) + 1);

        $weeks = array_fill_keys(range(1, 4), ['views' => 0, 'inter' => 0, 'posts' => 0]);
        foreach ($posts as $m) {
            $i = $weekOf((int) (($m['timestamp'] ?? 0) / 1000));
            $stat = $m['stat'] ?? [];

            $inter = (int) ($stat['reposts'] ?? 0) + (int) ($stat['forwards'] ?? 0);

            // комментарии к посту — доступная замена реакциям, которых в API нет
            $mid = $m['body']['mid'] ?? null;
            if ($this->withComments && $mid) {
                try {
                    $inter += $api->commentsCount((string) $mid);
                } catch (\Throwable) {
                    // комментарии могут быть выключены у канала — тихо пропускаем пост
                }
            }

            $weeks[$i]['views'] += (int) ($stat['views'] ?? 0);
            $weeks[$i]['inter'] += $inter;
            $weeks[$i]['posts']++;
        }

        // подписчики: текущее число для идущей недели, для прошедших — последний снимок внутри недели
        $current = (int) ($chat['participants_count'] ?? 0);
        $subsByWeek = $this->subscribersByWeek($project->id, $current, $start, $end);

        foreach ($weeks as $i => $w) {
            if ($subsByWeek !== null && $subsByWeek[$i] !== null) {
                $w['subs'] = $subsByWeek[$i];
            }
            $report->weeklyStats()->updateOrCreate(
                ['platform' => 'max', 'position' => $i],
                $w + ['label' => "Неделя $i"]
            );
        }
        $report->syncPlatformStats();

        // текущее число сразу в снимок за сегодня, чтобы динамика копилась
        ChannelSubscriberSnapshot::record($project->id, 'max', now(self::TZ)->toDateString(), $current);

        return [
            'title' => (string) ($chat['title'] ?? $project->max_channel_title),
            'posts' => array_sum(array_column($weeks, 'posts')),
            'views' => array_sum(array_column($weeks, 'views')),
            'inter' => array_sum(array_column($weeks, 'inter')),
            'subs' => $current ?: null,
            'noSnapshots' => $subsByWeek === null,
        ];
    }

    private function subscribersByWeek(int $projectId, int $current, Carbon $start, Carbon $end): ?array
    {
        $snaps = ChannelSubscriberSnapshot::where('project_id', $projectId)->where('platform', 'max')
            ->whereBetween('taken_on', [$start->copy()->startOfDay()->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('taken_on')->get()
            ->mapWithKeys(fn ($s) => [$s->taken_on->toDateString() => (int) $s->subscribers])->all();

        $today = Carbon::now(self::TZ)->startOfDay();
        $weeks = [];
        $any = false;
        foreach (range(1, 4) as $i) {
            $weekStart = $start->copy()->startOfDay()->addDays(($i - 1) * 7);
            $weekEnd = $i === 4 ? $end->copy()->startOfDay() : $weekStart->copy()->addDays(6);
            if ($weekStart->gt($today)) {
                $weeks[$i] = null;
                continue;
            }
            if ($weekEnd->gte($today)) {
                $weeks[$i] = $current;
                $any = true;
                continue;
            }
            $value = null;
            foreach ($snaps as $date => $n) {
                if ($date >= $weekStart->toDateString() && $date <= $weekEnd->toDateString()) {
                    $value = $n;
                }
            }
            $weeks[$i] = $value;
            $any = $any || $value !== null;
        }

        return $any ? $weeks : null;
    }
}
