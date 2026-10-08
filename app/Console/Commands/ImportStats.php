<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * РАЗОВАЯ загрузка исторической статистики в портал.
 *
 * Пишет каждый прошлый месяц ОДНОЙ строкой в weekly_stats (позиция 1, «Итог за месяц»)
 * с месячными суммами по площадке. Портал строит итоги месяца, карточки площадок,
 * 6-месячную динамику и ER именно из weekly_stats, поэтому так цифры подхватятся
 * везде. Поле subs = подписчиков всего на конец месяца (база для ER).
 * Недельную детализацию прошлых месяцев не грузим (в исходниках недели нарезаны
 * вразнобой, 5–9 на месяц, а портал рассчитан на 4) — текущие месяцы ведутся по неделям.
 *
 * Данные — из stats_import.csv (месячные итоги, посчитанные из ваших таблиц).
 *
 * Запуск из корня проекта:
 *   php artisan import:stats           — ПРОБНЫЙ прогон (без записи)
 *   php artisan import:stats --write   — запись в базу
 *
 * Повторный запуск безопасен: существующие проекты/месяцы переиспользуются,
 * строка «Итог за месяц» обновляется (updateOrCreate), дубликатов не будет.
 */
class ImportStats extends Command
{
    protected $signature = 'import:stats {--write : реально записать в базу} {--reset : перед импортом удалить существующие отчёты по проектам из CSV (чистая перезагрузка)}';
    protected $description = 'Разовый импорт исторической SMM-статистики (месячные итоги) в weekly_stats';

    private string $csvPath = 'storage/app/stats_import.csv';

    public function handle(): int
    {
        $write = $this->option('write');
        $path  = base_path($this->csvPath);

        if (!file_exists($path)) {
            $this->error("CSV не найден: {$path}");
            $this->line('Положите stats_import.csv в storage/app/');
            return self::FAILURE;
        }

        $this->info($write ? '=== РЕЖИМ ЗАПИСИ ===' : '=== ПРОБНЫЙ ПРОГОН (без записи) ===');

        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        $idx = array_flip($header);
        foreach (['project','year','month','platform','subs','views','reach','inter','leads','posts','stories'] as $col) {
            if (!isset($idx[$col])) { $this->error("В CSV нет колонки: {$col}"); return self::FAILURE; }
        }

        $projCache = [];
        $repCache  = [];
        $touched   = [];   // отчёты, которым пересчитать platform_stats
        $c = ['projects' => 0, 'reports' => 0, 'rows' => 0, 'skipped' => 0];

        if ($write) DB::beginTransaction();
        try {
            while (($row = fgetcsv($fh)) !== false) {
                $g   = fn($k) => $row[$idx[$k]] ?? '';
                $int = fn($k) => ($row[$idx[$k]] ?? '') === '' ? 0 : (int) $row[$idx[$k]];

                $name = trim($g('project'));
                $year = (int) $g('year');
                $month = (int) $g('month');
                $platform = trim($g('platform'));   // vk / ig / max / tg
                if ($name === '' || !$year || !$month || $platform === '') { $c['skipped']++; continue; }

                /* проект */
                if (!array_key_exists($name, $projCache)) {
                    $p = Project::where('name', $name)->first();
                    if (!$p) {
                        $c['projects']++;
                        if ($write) {
                            $p = Project::create([
                                'name'  => $name,
                                'slug'  => Str::slug($name) . '-' . Str::lower(Str::random(5)),
                                'color' => '#6C4CF0',
                                'is_active' => true,
                            ]);
                        }
                    }
                    // чистая перезагрузка: сносим старые отчёты этого проекта (вместе со статистикой по каскаду)
                    if ($write && $p && $this->option('reset')) {
                        $p->reports()->delete();
                    }
                    $projCache[$name] = $p;
                }
                $project = $projCache[$name];
                $pid = $project?->id ?? 'NEW';

                /* отчёт-месяц */
                $rk = "{$pid}-{$year}-{$month}";
                if (!array_key_exists($rk, $repCache)) {
                    $r = null;
                    if ($write) {
                        $r = Report::firstOrNew(['project_id' => $pid, 'year' => $year, 'month' => $month]);
                        if (!$r->exists) { $c['reports']++; $r->save(); }
                    } elseif (!$project || !Report::where(['project_id'=>$pid,'year'=>$year,'month'=>$month])->exists()) {
                        $c['reports']++;
                    }
                    $repCache[$rk] = $r;
                }
                $report = $repCache[$rk];

                /* месяц -> одна строка-неделя «Итог за месяц» */
                $c['rows']++;
                if ($write && $report) {
                    $report->weeklyStats()->updateOrCreate(
                        ['platform' => $platform, 'position' => 1],
                        [
                            'label'   => 'Итог за месяц',
                            'subs'    => $int('subs'),      // подписчиков всего (конец месяца, база ER)
                            'views'   => $int('views'),
                            'reach'   => $int('reach'),
                            'inter'   => $int('inter'),
                            'leads'   => $int('leads'),
                            'posts'   => $int('posts'),
                            'stories' => $int('stories'),
                        ]
                    );
                    $touched[$rk] = $report;
                }
            }

            // держим platform_stats в согласии с weekly (на случай, если где-то используется)
            foreach ($touched as $report) {
                if (method_exists($report, 'syncPlatformStats')) $report->syncPlatformStats();
            }

            if ($write) DB::commit();
        } catch (\Throwable $e) {
            if ($write) DB::rollBack();
            $this->error('ОШИБКА, откат: ' . $e->getMessage());
            return self::FAILURE;
        }
        fclose($fh);

        $this->newLine();
        $this->table(['Что', 'Кол-во'], [
            ['Новых проектов',        $c['projects']],
            ['Новых отчётов-месяцев', $c['reports']],
            ['Строк статистики',      $c['rows']],
            ['Пропущено',             $c['skipped']],
        ]);
        $this->line($write
            ? 'Готово. Откройте проекты — итоги, карточки площадок и динамика должны заполниться.'
            : 'Пробный прогон. Если верно — запустите:  php artisan import:stats --write');

        return self::SUCCESS;
    }
}
