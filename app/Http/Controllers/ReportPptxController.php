<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ReportPptxController extends Controller
{
    private array $platforms = ['vk', 'ig', 'max', 'yt', 'tg'];
    private array $names = ['vk' => 'ВКонтакте', 'ig' => 'Инстаграм', 'max' => 'Макс', 'yt' => 'YouTube', 'tg' => 'Телеграм'];

    public function download(Report $report)
    {
        $report->load(['project', 'platformStats', 'tasks', 'contentItems']);
        $prev = $report->previousReport();
        $prev?->load('platformStats');

        // площадки, которых у клиента нет (YouTube без канала и цифр), в презентацию не попадают
        $platforms = array_values(array_filter($this->platforms, fn ($p) => $report->platformEnabled($p)));

        $map = function ($rep) use ($platforms) {
            $out = [];
            foreach ($platforms as $p) {
                $ps = $rep?->platformStats->firstWhere('platform', $p);
                if ($ps) {
                    $out[$p] = ['subs' => $ps->subs, 'views' => $ps->views, 'reach' => $ps->reach,
                        'inter' => $ps->inter, 'er' => $ps->er, 'leads' => $ps->leads,
                        'posts' => $ps->posts, 'stories' => $ps->stories];
                }
            }
            return $out;
        };

        // series: непрерывный ряд месяцев (до 6) по текущий отчёт; месяцы без отчёта — нулями
        $months = ['', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
        $history = Report::monthWindow($report, 6, ['platformStats', 'weeklyStats']);

        $series = $history->map(function ($h) use ($months, $platforms) {
            $row = ['k' => $months[$h['month']]];
            $r = $h['report'];
            foreach ($platforms as $p) {
                // берём те же итоги, что показывает сайт (недели, с откатом на месячные)
                $stats = $r ? ($r->platform_totals[$p] ?? []) : [];
                $row[$p] = [
                    'subs' => $stats['subs'] ?? 0,
                    'views' => $stats['views'] ?? 0,
                    'inter' => $stats['inter'] ?? 0,
                ];
            }
            return $row;
        })->all();

        // абсолютные пути картинок для вставки в презентацию
        $img = fn (?string $rel) => $rel && file_exists(storage_path('app/public/'.$rel))
            ? storage_path('app/public/'.$rel)
            : null;

        $payload = [
            'client' => $report->project->name,
            'period' => $report->period_label,
            'period_month' => $months[$report->month],
            'prev_month' => $prev ? $months[$prev->month] : null,
            'platformNames' => $this->names,
            'current' => $map($report),
            'previous' => ['label' => $prev?->period_label, 'stats' => $prev ? $map($prev) : null],
            'series' => $series,
            'tasks' => $report->tasks->map(fn ($t) => ['title' => $t->title, 'plan' => $t->plan, 'fact' => $t->fact, 'status' => $t->status])->all(),
            'content' => $report->contentItems->map(fn ($c) => ['platform' => $c->platform, 'kind' => $c->kind, 'title' => $c->title, 'views' => $c->views, 'reactions' => $c->reactions, 'comments' => $c->comments, 'reposts' => $c->reposts, 'insight' => $c->insight, 'image' => $img($c->image)])->all(),
            'business' => $report->business,
            'metric_notes' => $report->metric_notes,
            'summary' => $report->summary,
            'plan_next' => $report->plan_next,
            'community' => collect(ReportController::communityByPlatform($report->community))
                ->map(fn ($items) => collect($items)->map(fn ($c) => [
                    'caption' => $c['caption'] ?? '',
                    'image' => $img($c['image'] ?? null),
                ])->values()->all())
                ->all(),
        ];

        $stamp = Str::random(8);
        $jsonPath = storage_path("app/report_{$stamp}.json");
        $outPath = storage_path("app/report_{$stamp}.pptx");
        file_put_contents($jsonPath, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $result = Process::path(base_path())->timeout(120)
            ->run(['node', 'pptx/generate.cjs', $jsonPath, $outPath]);

        @unlink($jsonPath);

        if (! $result->successful() || ! file_exists($outPath)) {
            abort(500, 'Не удалось сформировать презентацию. Убедитесь, что установлен Node.js и выполнен npm install. '.$result->errorOutput());
        }

        $name = 'Отчёт_'.$report->project->name.'_'.$report->period_label.'.pptx';

        return response()->download($outPath, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ])->deleteFileAfterSend(true);
    }
}
