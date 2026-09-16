<?php

namespace App\Console\Commands;

use App\Models\ChannelSubscriberSnapshot;
use App\Models\Project;
use App\Services\Max\MaxChats;
use Illuminate\Console\Command;

// MAX не хранит историю подписчиков — записываем число раз в день по каждому проекту с каналом
class MaxSnapshotSubscribers extends Command
{
    protected $signature = 'max:snapshot-subscribers';

    protected $description = 'Сохранить число подписчиков каналов MAX всех проектов на сегодня';

    public function handle(): int
    {
        $projects = Project::whereNotNull('max_channel_id')->get();
        if ($projects->isEmpty() || !MaxChats::configured()) {
            $this->info('Нет проектов с каналом MAX или не задан ключ бота.');

            return self::SUCCESS;
        }

        $api = MaxChats::api();
        $failed = 0;
        foreach ($projects as $project) {
            try {
                $chat = $api->chat((int) $project->max_channel_id);
            } catch (\Throwable $e) {
                $this->warn("{$project->name}: {$e->getMessage()}");
                $failed++;
                continue;
            }
            $count = (int) ($chat['participants_count'] ?? 0);
            ChannelSubscriberSnapshot::record($project->id, 'max', now()->toDateString(), $count);
            $this->info("{$project->name} ({$project->max_channel_title}): {$count}");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
