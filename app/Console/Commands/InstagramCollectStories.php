<?php

namespace App\Console\Commands;

use App\Models\InstagramStory;
use App\Models\Project;
use App\Services\InstagramApi;
use App\Services\InstagramOAuth;
use Carbon\Carbon;
use Illuminate\Console\Command;

// Instagram отдаёт сторис только пока они активны (24 часа), поэтому собираем их
// по расписанию для всех подключённых проектов — иначе в отчёте их не посчитать
class InstagramCollectStories extends Command
{
    protected $signature = 'instagram:collect-stories';

    protected $description = 'Сохранить активные сторис всех подключённых к Instagram проектов';

    public function handle(): int
    {
        $projects = Project::whereNotNull('instagram_token')->get();
        if ($projects->isEmpty()) {
            $this->info('Нет проектов с подключённым Instagram.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($projects as $project) {
            $token = InstagramOAuth::validToken($project);
            if (!$token) {
                $this->warn("{$project->name}: токен Instagram истёк, нужно переподключение");
                $failed++;
                continue;
            }

            try {
                $stories = (new InstagramApi($token))->activeStories();
            } catch (\Throwable $e) {
                $this->warn("{$project->name}: {$e->getMessage()}");
                $failed++;
                continue;
            }

            $new = 0;
            foreach ($stories as $s) {
                $row = InstagramStory::firstOrCreate(
                    ['project_id' => $project->id, 'story_id' => $s['id']],
                    ['published_at' => Carbon::createFromTimestamp($s['published_at'], config('app.timezone'))]
                );
                $new += $row->wasRecentlyCreated ? 1 : 0;
            }
            $this->info("{$project->name} (@{$project->instagram_username}): активных сторис ".count($stories).", новых {$new}");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
