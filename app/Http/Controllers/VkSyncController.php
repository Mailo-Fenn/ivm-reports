<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Setting;
use App\Services\SubscriberTotals;
use App\Services\VkApi;
use App\Services\VkApiException;
use App\Services\VkOAuth;
use Carbon\Carbon;

class VkSyncController extends Controller
{
    // статистика ВК привязана к московским суткам
    private const TZ = 'Europe/Moscow';

    public function sync(Report $report)
    {
        // приоритет: токен администратора через VK ID (единственный, кому доступны
        // stats.get и wall.get), дальше — ключ проекта и общий токен как запасные
        $token = VkOAuth::validAccessToken() ?: ($report->project->vk_token ?: Setting::get('vk_token'));
        if (!$token) {
            return back()->with('error', 'VK не подключён — авторизуйтесь через VK ID на странице «Настройки»');
        }

        if (!$report->project->vk_group) {
            return back()->with('error', 'У проекта не указано сообщество ВК — заполните поле в настройках проекта');
        }

        $vk = new VkApi($token);

        $start = Carbon::create($report->year, $report->month, 1, 0, 0, 0, self::TZ);
        $end = $start->copy()->endOfMonth();

        try {
            $group = $vk->group($report->project->vk_group);
            $posts = $vk->wallPosts($group['id'], $start->timestamp, $end->timestamp);
        } catch (VkApiException $e) {
            return back()->with('error', 'Ошибка VK API: ' . $e->getMessage());
        } catch (\Throwable) {
            return back()->with('error', 'Не удалось связаться с VK API — проверьте соединение');
        }

        // stats.get доступен не каждому токену: 27 — ключ сообщества, 1051 — токен VK ID
        // без права stats; в этих случаях подтягиваем только посты, не трогая остальные цифры
        $statsAvailable = true;
        $days = [];
        try {
            // до сегодня, а не до конца месяца: по приросту после месяца восстанавливаем число подписчиков на его конец
            $days = $vk->stats($group['id'], $start->timestamp, max($end->timestamp, time()));
        } catch (VkApiException $e) {
            if (!in_array($e->getCode(), [27, 1051])) {
                return back()->with('error', 'Ошибка VK API: ' . $e->getMessage());
            }
            $statsAvailable = false;
        } catch (\Throwable) {
            return back()->with('error', 'Не удалось связаться с VK API — проверьте соединение');
        }

        // недели отчёта: дни 1–7, 8–14, 15–21, 22 — конец месяца
        $weekOf = fn (int $ts) => min(4, intdiv(Carbon::createFromTimestamp($ts, self::TZ)->day - 1, 7) + 1);

        $weeks = array_fill_keys(range(1, 4), ['views' => 0, 'reach' => 0, 'inter' => 0, 'posts' => 0]);

        $netByDay = [];
        foreach ($days as $d) {
            if (!isset($d['period_from'])) {
                continue;
            }
            $ts = (int) $d['period_from'];
            $a = $d['activity'] ?? [];
            $netByDay[Carbon::createFromTimestamp($ts, self::TZ)->toDateString()] = ($a['subscribed'] ?? 0) - ($a['unsubscribed'] ?? 0);
            if ($ts > $end->timestamp) {
                continue;
            }
            $i = $weekOf($ts);
            $weeks[$i]['inter'] += ($a['likes'] ?? 0) + ($a['comments'] ?? 0) + ($a['copies'] ?? 0);
            $weeks[$i]['views'] += $d['visitors']['views'] ?? 0;
            $weeks[$i]['reach'] += $d['reach']['reach'] ?? 0;
        }

        // подписчики — общее число на конец каждой недели (сейчас минус прирост после неё)
        $subsByWeek = $statsAvailable
            ? SubscriberTotals::byWeek((int) ($group['members_count'] ?? 0), $netByDay, $start, $end)
            : null;

        foreach ($posts as $p) {
            $weeks[$weekOf((int) $p['date'])]['posts']++;
        }

        // leads и stories из API недоступны — эти поля не трогаем;
        // без stats.get обновляем только посты, сохраняя введённые вручную цифры
        foreach ($weeks as $i => $w) {
            if ($subsByWeek !== null && $subsByWeek[$i] !== null) {
                $w['subs'] = $subsByWeek[$i];
            }
            $report->weeklyStats()->updateOrCreate(
                ['platform' => 'vk', 'position' => $i],
                ($statsAvailable ? $w : ['posts' => $w['posts']]) + ['label' => "Неделя $i"]
            );
        }

        $report->syncPlatformStats();

        if (!$statsAvailable) {
            return back()->with('success', "Посты «{$group['name']}» за {$report->period_label} подтянуты. Статистика (просмотры, охваты, подписчики) текущему токену недоступна — VK не выдал приложению право «stats», нужна заявка в поддержку VK ID.");
        }

        return back()->with('success', "Данные ВК подтянуты: «{$group['name']}», {$report->period_label}");
    }
}
