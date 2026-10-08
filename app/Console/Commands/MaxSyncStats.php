<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Report;
use App\Services\Max\MaxChats;
use App\Services\Max\MaxReportSync;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Автоматический сбор статистики каналов MAX в отчёты — то же, что кнопка
 * «Подтянуть из MAX», но по расписанию и сразу по всем проектам.
 *
 * Обновляет отчёт за указанный месяц (по умолчанию — текущий).
 * Отчёты сам НЕ создаёт: если отчёта за месяц ещё нет, проект пропускается,
 * чтобы не плодить пустые месяцы без задач по тарифу.
 */
class MaxSyncStats extends Command
{
    protected $signature = 'max:sync-stats
        {--month= : месяц в формате ГГГГ-ММ (по умолчанию текущий)}
        {--no-comments : не считать комментарии (быстрее, но вовлечённость будет ниже)}';

    protected $description = 'Подтянуть статистику каналов MAX (просмотры, вовлечённость, подписчики) в отчёты';

    public function handle(MaxReportSync $syncer): int
    {
        if (!MaxChats::configured()) {
            $this->warn('Не задан ключ MAX-бота — заполните его на странице «Настройки».');

            return self::SUCCESS;
        }

        $month = $this->option('month');
        $date = $month
            ? Carbon::createFromFormat('Y-m', $month, MaxReportSync::TZ)
            : Carbon::now(MaxReportSync::TZ);
        if (!$date) {
            $this->error('Неверный формат --month, нужен ГГГГ-ММ (например 2026-10).');

            return self::FAILURE;
        }

        $syncer->withComments = !$this->option('no-comments');

        $projects = Project::whereNotNull('max_channel_id')->orderBy('name')->get();
        if ($projects->isEmpty()) {
            $this->info('Нет проектов с подключённым каналом MAX.');

            return self::SUCCESS;
        }

        $done = $skipped = $failed = 0;

        foreach ($projects as $project) {
            $report = Report::where('project_id', $project->id)
                ->where('year', $date->year)->where('month', $date->month)->first();

            if (!$report) {
                $this->line("— {$project->name}: отчёта за {$date->year}-{$date->format('m')} нет, пропускаю");
                $skipped++;
                continue;
            }

            try {
                $res = $syncer->run($report);
                $this->info(sprintf(
                    '✓ %s («%s»): просмотры %s, вовлечённость %s, подписчики %s, постов %d',
                    $project->name,
                    $res['title'],
                    number_format($res['views'], 0, ',', ' '),
                    number_format($res['inter'], 0, ',', ' '),
                    $res['subs'] !== null ? number_format($res['subs'], 0, ',', ' ') : '—',
                    $res['posts'],
                ));
                $done++;
            } catch (\Throwable $e) {
                $this->warn("× {$project->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->line("Обновлено: {$done} · пропущено: {$skipped} · с ошибкой: {$failed}");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
