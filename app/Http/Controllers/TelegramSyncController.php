<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\TelegramSubscriberSnapshot;
use App\Services\SubscriberTotals;
use App\Services\TelegramException;
use App\Services\TelegramStats;
use Carbon\Carbon;

class TelegramSyncController extends Controller
{
    // недели отчёта считаем по московским суткам, как и для других площадок
    private const TZ = 'Europe/Moscow';

    public function sync(Report $report, TelegramStats $tg)
    {
        $project = $report->project;

        if (!$project->telegram_channel) {
            return back()->with('error', 'У проекта не указан канал Telegram — заполните поле в настройках проекта');
        }
        if (!TelegramStats::connected()) {
            return back()->with('error', 'Аккаунт Telegram не подключён — войдите на странице «Настройки»');
        }

        $start = Carbon::create($report->year, $report->month, 1, 0, 0, 0, self::TZ);
        $end = $start->copy()->endOfMonth();

        try {
            $data = $tg->stats($project->telegram_channel, $start->timestamp, $end->timestamp);
        } catch (TelegramException $e) {
            return back()->with('error', 'Telegram: '.$e->getMessage());
        } catch (\Throwable) {
            return back()->with('error', 'Не удалось получить данные Telegram — проверьте, что на сервере установлен Python и telethon');
        }

        $weekOf = fn (int $ts) => min(4, intdiv(Carbon::createFromTimestamp($ts, self::TZ)->day - 1, 7) + 1);

        // просмотры, взаимодействия (реакции + пересылки + комментарии) и посты — по неделям публикации
        $weeks = array_fill_keys(range(1, 4), ['views' => 0, 'inter' => 0, 'posts' => 0]);
        foreach ($data['posts'] ?? [] as $p) {
            $i = $weekOf((int) $p['date']);
            $weeks[$i]['views'] += (int) $p['views'];
            $weeks[$i]['inter'] += (int) $p['reactions'] + (int) $p['forwards'] + (int) $p['replies'];
            $weeks[$i]['posts']++;
        }

        // подписчики — общее число на конец недели: из встроенной статистики канала (≥500 подписчиков,
        // права админа) через число сейчас минус прирост после недели, иначе — по ежедневным снимкам
        [$subsByWeek, $subsSource] = $this->subscribers(
            $report, $data['followers_by_day'] ?? null, (int) ($data['channel']['subscribers'] ?? 0), $start, $end
        );

        // охват Telegram не отдаёт, заявки и сторис к каналам не относятся — эти поля не трогаем
        foreach ($weeks as $i => $w) {
            if ($subsByWeek !== null && $subsByWeek[$i] !== null) {
                $w['subs'] = $subsByWeek[$i];
            }
            $report->weeklyStats()->updateOrCreate(
                ['platform' => 'tg', 'position' => $i],
                $w + ['label' => "Неделя $i"]
            );
        }

        $report->syncPlatformStats();

        $title = $data['channel']['title'] ?? $project->telegram_channel;
        $msg = "Данные Telegram подтянуты: «{$title}», {$report->period_label}";
        if ($subsByWeek === null) {
            $msg .= '. Подписчики не обновлены: встроенная статистика канала недоступна или не покрывает этот месяц (нужно ≥500 подписчиков и права администратора), а ежедневных снимков за него нет — цифры остались как были';
        } elseif ($subsSource === 'snapshots') {
            $msg .= ' (подписчики — по ежедневным снимкам)';
        }

        return back()->with('success', $msg);
    }

    // [array|null по неделям (число на конец недели или null), источник]
    private function subscribers(Report $report, ?array $byDay, int $current, Carbon $start, Carbon $end): array
    {
        // 1) встроенная статистика: график покрывает ограниченный период, поэтому недели раньше его начала не трогаем
        if ($byDay) {
            $weeks = SubscriberTotals::byWeek($current, $byDay, $start, $end, Carbon::parse(min(array_keys($byDay)), self::TZ));
            if ($weeks && array_filter($weeks, fn ($v) => $v !== null)) {
                return [$weeks, 'stats'];
            }
        }

        // 2) ежедневные снимки: число на конец недели = последний снимок внутри этой недели
        $snaps = TelegramSubscriberSnapshot::where('project_id', $report->project_id)
            // в базе дата хранится с временем 00:00:00, поэтому границы задаём датой-временем
            ->whereBetween('taken_on', [$start->copy()->startOfDay()->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('taken_on')
            ->get()
            ->mapWithKeys(fn ($s) => [$s->taken_on->toDateString() => (int) $s->subscribers])
            ->all();

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
            // текущая неделя — число на сейчас
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

        return $any ? [$weeks, 'snapshots'] : [null, null];
    }
}
