<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\TelegramSubscriberSnapshot;
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

        // прирост подписчиков: встроенная статистика канала (≥500 подписчиков, права админа),
        // иначе — ежедневные снимки команды telegram:snapshot-subscribers
        [$subsByWeek, $subsSource] = $this->subscribers($report, $data['followers_by_day'] ?? null, $start, $end, $weekOf);

        // охват Telegram не отдаёт, заявки и сторис к каналам не относятся — эти поля не трогаем
        foreach ($weeks as $i => $w) {
            if ($subsByWeek !== null) {
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
            $msg .= '. Подписчики не обновлены: у канала нет встроенной статистики (нужно ≥500 подписчиков и права администратора), а ежедневных снимков за этот месяц ещё нет — цифры остались как были';
        } elseif ($subsSource === 'snapshots') {
            $msg .= ' (подписчики — по ежедневным снимкам)';
        }

        return back()->with('success', $msg);
    }

    // [array|null по неделям, источник]
    private function subscribers(Report $report, ?array $byDay, Carbon $start, Carbon $end, callable $weekOf): array
    {
        $weeks = array_fill_keys(range(1, 4), 0);

        if ($byDay) {
            $inMonth = array_filter($byDay, fn ($v, $d) => $d >= $start->toDateString() && $d <= $end->toDateString(), ARRAY_FILTER_USE_BOTH);
            if ($inMonth) {
                foreach ($inMonth as $date => $net) {
                    $weeks[$weekOf(strtotime($date))] += (int) $net;
                }

                return [$weeks, 'stats'];
            }
        }

        // снимки: прирост за неделю = снимок на конец недели − снимок на конец предыдущей недели
        $snaps = TelegramSubscriberSnapshot::where('project_id', $report->project_id)
            // в базе дата хранится с временем 00:00:00, поэтому границы задаём датой-временем
            ->whereBetween('taken_on', [$start->copy()->subDay()->startOfDay()->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('taken_on')
            ->pluck('subscribers', 'taken_on')
            ->mapWithKeys(fn ($v, $k) => [Carbon::parse($k)->toDateString() => (int) $v])
            ->all();
        if (count($snaps) < 2) {
            return [null, null];
        }

        $valueOn = function (string $date) use ($snaps) {
            // ближайший снимок не позже даты
            $best = null;
            foreach ($snaps as $d => $v) {
                if ($d <= $date) {
                    $best = $v;
                }
            }

            return $best;
        };

        $prev = $valueOn($start->copy()->subDay()->toDateString()) ?? reset($snaps);
        foreach (range(1, 4) as $i) {
            $weekEnd = $i === 4 ? $end->copy() : $start->copy()->addDays($i * 7 - 1);
            $cur = $valueOn($weekEnd->toDateString()) ?? $prev;
            $weeks[$i] = $cur - $prev;
            $prev = $cur;
        }

        return [$weeks, 'snapshots'];
    }
}
