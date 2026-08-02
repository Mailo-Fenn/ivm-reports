<?php

namespace App\Console\Commands;

use App\Models\WeeklyStat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateWeeklyStatsPlatforms extends Command
{
    protected $signature = 'reports:migrate-weekly-platforms';

    protected $description = 'Convert weekly stats without platform into vk/ig/max records';

    public function handle(): int
    {
        $rows = WeeklyStat::whereNull('platform')->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows) {

            foreach ($rows as $row) {

                foreach (['vk', 'ig', 'max'] as $platform) {

                    WeeklyStat::create([
                        'report_id' => $row->report_id,
                        'platform'  => $platform,
                        'position'  => $row->position,
                        'label'     => $row->label,

                        'subs'      => $row->subs,
                        'views'     => $row->views,
                        'reach'     => $row->reach,
                        'inter'     => $row->inter,
                        'leads'     => $row->leads,
                        'posts'     => $row->posts,
                        'stories'   => $row->stories,
                    ]);
                }

                $row->delete();
            }
        });

        $this->info("Migrated {$rows->count()} weekly records.");

        return self::SUCCESS;
    }
}