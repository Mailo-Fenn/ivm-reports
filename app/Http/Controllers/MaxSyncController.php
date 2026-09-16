<?php

namespace App\Http\Controllers;

use App\Models\ChannelSubscriberSnapshot;
use App\Models\Report;
use App\Services\Max\MaxChats;
use App\Services\Max\MaxException;
use Carbon\Carbon;

// Статистика канала MAX для отчёта: посты с просмотрами (и репостами, если API их отдаёт),
// подписчики — по ежедневным снимкам, потому что истории подписчиков у MAX нет
class MaxSyncController extends Controller
{
    private const TZ = 'Europe/Moscow';

    public function sync(Report $report)
    {
        $project = $report->project;
        if (!$project->max_channel_id) {
            return back()->with('error', 'У проекта не выбран канал MAX — выберите его в настройках проекта');
        }
        if (!MaxChats::configured()) {
            return back()->with('error', 'Не задан ключ MAX-бота — заполните его на странице «Настройки»');
        }

        $start = Carbon::create($report->year, $report->month, 1, 0, 0, 0, self::TZ);
        $end = $start->copy()->endOfMonth();

        try {
            $api = MaxChats::api();
            $chat = $api->chat((int) $project->max_channel_id);
            $posts = $api->messagesBetween((int) $project->max_channel_id, $start->timestamp, $end->timestamp);
        } catch (MaxException $e) {
            return back()->with('error', 'MAX: '.match ($e->getCode()) {
                401 => 'ключ бота недействителен — проверьте его в настройках',
                403 => 'бот не администратор канала — попросите клиента добавить его администратором',
                404 => 'канал не найден — обновите список каналов в настройках',
                default => $e->getMessage(),
            });
        } catch (\Throwable) {
            return back()->with('error', 'Не удалось связаться с MAX API — проверьте соединение');
        }

        $weekOf = fn (int $ts) => min(4, intdiv(Carbon::createFromTimestamp($ts, self::TZ)->day - 1, 7) + 1);

        // просмотры и репосты (если есть в stat) — по неделе публикации
        $weeks = array_fill_keys(range(1, 4), ['views' => 0, 'inter' => 0, 'posts' => 0]);
        foreach ($posts as $m) {
            $i = $weekOf((int) (($m['timestamp'] ?? 0) / 1000));
            $stat = $m['stat'] ?? [];
            $weeks[$i]['views'] += (int) ($stat['views'] ?? 0);
            $weeks[$i]['inter'] += (int) ($stat['reposts'] ?? 0) + (int) ($stat['forwards'] ?? 0);
            $weeks[$i]['posts']++;
        }

        // подписчики: число сейчас для текущей недели, для прошедших — последний снимок внутри недели
        $current = (int) ($chat['participants_count'] ?? 0);
        $subsByWeek = $this->subscribersByWeek($report->project_id, $current, $start, $end);

        // охваты и заявки MAX не отдаёт — не трогаем
        foreach ($weeks as $i => $w) {
            if ($subsByWeek !== null && $subsByWeek[$i] !== null) {
                $w['subs'] = $subsByWeek[$i];
            }
            $report->weeklyStats()->updateOrCreate(['platform' => 'max', 'position' => $i], $w + ['label' => "Неделя $i"]);
        }
        $report->syncPlatformStats();

        // текущее число — сразу в снимок за сегодня, чтобы динамика копилась
        ChannelSubscriberSnapshot::record($project->id, 'max', now(self::TZ)->toDateString(), $current);

        $msg = "Данные MAX подтянуты: «".($chat['title'] ?? $project->max_channel_title)."», {$report->period_label}";
        if ($subsByWeek === null) {
            $msg .= '. Подписчики за прошедшие недели не обновлены: снимков за этот месяц нет, они копятся с момента подключения';
        }

        return back()->with('success', $msg);
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
