<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\TelegramSubscriberSnapshot;
use App\Services\TelegramStats;
use Illuminate\Console\Command;

// Telegram не отдаёт динамику подписчиков каналам меньше 500 человек, поэтому раз в день
// записываем текущее число — из снимков считается прирост по неделям в отчёте
class TelegramSnapshotSubscribers extends Command
{
    protected $signature = 'telegram:snapshot-subscribers';

    protected $description = 'Сохранить число подписчиков каналов Telegram всех проектов на сегодня';

    public function handle(TelegramStats $tg): int
    {
        $projects = Project::whereNotNull('telegram_channel')->get();
        if ($projects->isEmpty()) {
            $this->info('Нет проектов с каналом Telegram.');

            return self::SUCCESS;
        }
        if (!TelegramStats::connected()) {
            $this->warn('Аккаунт Telegram не подключён — снимки не сделаны.');

            return self::FAILURE;
        }

        $failed = 0;
        foreach ($projects as $project) {
            try {
                $count = $tg->count($project->telegram_channel);
            } catch (\Throwable $e) {
                $this->warn("{$project->name}: {$e->getMessage()}");
                $failed++;
                continue;
            }

            TelegramSubscriberSnapshot::updateOrCreate(
                ['project_id' => $project->id, 'taken_on' => now()->toDateString()],
                ['subscribers' => $count]
            );
            $this->info("{$project->name} ({$project->telegram_channel}): {$count}");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
