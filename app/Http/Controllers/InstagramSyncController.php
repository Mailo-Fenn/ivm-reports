<?php

namespace App\Http\Controllers;

use App\Models\InstagramStory;
use App\Models\Report;
use App\Services\InstagramApi;
use App\Services\InstagramApiException;
use App\Services\InstagramOAuth;
use Carbon\Carbon;

class InstagramSyncController extends Controller
{
    // недели отчёта считаем по московским суткам, как и для других площадок
    private const TZ = 'Europe/Moscow';

    public function sync(Report $report)
    {
        $project = $report->project;

        if (!$project->instagram_token) {
            return back()->with('error', 'Instagram не подключён к проекту — нажмите «Подключить Instagram» в настройках проекта');
        }

        $token = InstagramOAuth::validToken($project);
        if (!$token) {
            return back()->with('error', 'Токен Instagram истёк — переподключите Instagram в настройках проекта');
        }

        $ig = new InstagramApi($token);

        $start = Carbon::create($report->year, $report->month, 1, 0, 0, 0, self::TZ);
        $end = $start->copy()->endOfMonth();

        // границы недель отчёта: дни 1–7, 8–14, 15–21, 22 — конец месяца
        $ranges = [];
        foreach (range(1, 4) as $i) {
            $from = $start->copy()->addDays(($i - 1) * 7);
            $to = $i === 4 ? $end->copy() : $from->copy()->addDays(6)->endOfDay();
            $ranges[$i] = [$from->timestamp, $to->timestamp];
        }

        $weeks = array_fill_keys(range(1, 4), ['views' => 0, 'reach' => 0, 'inter' => 0, 'posts' => 0]);
        $missing = [];

        // охват, просмотры и взаимодействия — суммарно за каждую неделю
        try {
            foreach ($ranges as $i => [$from, $to]) {
                $t = $ig->totals($from, $to);
                $weeks[$i]['reach'] = $t['reach'] ?? 0;
                $weeks[$i]['views'] = $t['views'] ?? 0;
                $weeks[$i]['inter'] = $t['total_interactions'] ?? 0;
            }
        } catch (InstagramApiException $e) {
            if ($e->getCode() === 190) {
                return back()->with('error', 'Instagram отозвал токен — переподключите Instagram в настройках проекта');
            }

            return back()->with('error', 'Ошибка Instagram API: '.$e->getMessage());
        } catch (\Throwable) {
            return back()->with('error', 'Не удалось связаться с Instagram API — проверьте соединение');
        }

        // публикации за месяц
        try {
            foreach ($ig->mediaBetween($start->timestamp, $end->timestamp) as $m) {
                $weeks[$this->weekOf($m['published_at'])]['posts']++;
            }
        } catch (\Throwable) {
            $missing[] = 'публикации';
            foreach ($weeks as &$w) {
                unset($w['posts']);
            }
            unset($w);
        }

        // новые подписчики: Meta отдаёт их только за последние 30 дней, поэтому берём то, что есть
        $subsByWeek = null;
        try {
            $days = $ig->newFollowersByDay($start->timestamp, $end->timestamp);
            if ($days) {
                $subsByWeek = array_fill_keys(range(1, 4), 0);
                foreach ($days as $date => $n) {
                    $subsByWeek[$this->weekOf(strtotime($date))] += $n;
                }
            }
        } catch (\Throwable) {
            // метрика недоступна — подписчиков не трогаем
        }
        if ($subsByWeek === null) {
            $missing[] = 'подписчики';
        }

        // сторис — из локальной копилки, которую наполняет команда instagram:collect-stories
        $storiesByWeek = null;
        if (InstagramStory::where('project_id', $project->id)->exists()) {
            $storiesByWeek = array_fill_keys(range(1, 4), 0);
            $stories = InstagramStory::where('project_id', $project->id)
                ->whereBetween('published_at', [$start, $end])
                ->get();
            foreach ($stories as $s) {
                $storiesByWeek[$this->weekOf($s->published_at->timestamp)]++;
            }
        } else {
            $missing[] = 'сторис';
        }

        // заявки (leads) Instagram API не отдаёт — поле не трогаем
        foreach ($weeks as $i => $w) {
            if ($subsByWeek !== null) {
                $w['subs'] = $subsByWeek[$i];
            }
            if ($storiesByWeek !== null) {
                $w['stories'] = $storiesByWeek[$i];
            }
            $report->weeklyStats()->updateOrCreate(
                ['platform' => 'ig', 'position' => $i],
                $w + ['label' => "Неделя $i"]
            );
        }

        $report->syncPlatformStats();

        $msg = "Данные Instagram подтянуты: @{$project->instagram_username}, {$report->period_label}";
        if ($missing) {
            $msg .= '. Не обновлены: '.implode(', ', $missing).' — эти цифры остались как были';
        }

        return back()->with('success', $msg);
    }

    private function weekOf(int $ts): int
    {
        return min(4, intdiv(Carbon::createFromTimestamp($ts, self::TZ)->day - 1, 7) + 1);
    }
}
