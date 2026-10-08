<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\Max\MaxChats;
use App\Services\Max\MaxException;
use App\Services\Max\MaxReportSync;

// Кнопка «Подтянуть из MAX» в отчёте. Сам сбор — в App\Services\Max\MaxReportSync,
// тот же код работает по расписанию (команда max:sync-stats).
class MaxSyncController extends Controller
{
    public function sync(Report $report, MaxReportSync $syncer)
    {
        $project = $report->project;
        if (!$project->max_channel_id) {
            return back()->with('error', 'У проекта не выбран канал MAX — выберите его в настройках проекта');
        }
        if (!MaxChats::configured()) {
            return back()->with('error', 'Не задан ключ MAX-бота — заполните его на странице «Настройки»');
        }

        try {
            $res = $syncer->run($report);
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

        $msg = "Данные MAX подтянуты: «{$res['title']}», {$report->period_label}";
        if ($res['noSnapshots']) {
            $msg .= '. Подписчики за прошедшие недели не обновлены: снимков за этот месяц нет, они копятся с момента подключения';
        }

        return back()->with('success', $msg);
    }
}
