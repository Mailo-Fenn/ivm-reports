<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ReportPptxController extends Controller
{
    private array $platforms = ['vk', 'ig', 'max'];
    private array $names = ['vk' => 'ВКонтакте', 'ig' => 'Инстаграм', 'max' => 'Макс'];

    public function download(Report $report)
    {
        $report->load(['project', 'platformStats', 'tasks', 'contentItems']);
        $prev = $report->previousReport();
        $prev?->load('platformStats');

        $map = function ($rep) {
            $out = [];
            foreach ($this->platforms as $p) {
                $ps = $rep?->platformStats->firstWhere('platform', $p);
                if ($ps) {
                    $out[$p] = ['subs' => $ps->subs, 'views' => $ps->views, 'reach' => $ps->reach,
                        'inter' => $ps->inter, 'er' => $ps->er, 'leads' => $ps->leads,
                        'posts' => $ps->posts, 'stories' => $ps->stories];
                }
            }
            return $out;
        };

        // series: last 6 months views per platform
        $short = ['', 'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];
        $history = Report::where('project_id', $report->project_id)
            ->where(fn ($q) => $q->where('year', '<', $report->year)
                ->orWhere(fn ($q2) => $q2->where('year', $report->year)->where('month', '<=', $report->month)))
            ->with('platformStats')->orderByDesc('year')->orderByDesc('month')->limit(6)->get()->reverse()->values();

        $series = $history->map(function ($r) use ($short) {
            $row = ['k' => $short[$r->month]];
            foreach ($this->platforms as $p) {
                $ps = $r->platformStats->firstWhere('platform', $p);
                $row[$p] = ['views' => $ps ? $ps->views : 0];
            }
            return $row;
        })->all();

        $payload = [
            'client' => $report->project->name,
            'period' => $report->period_label,
            'platformNames' => $this->names,
            'current' => $map($report),
            'previous' => ['label' => $prev?->period_label, 'stats' => $prev ? $map($prev) : null],
            'series' => $series,
            'tasks' => $report->tasks->map(fn ($t) => ['title' => $t->title, 'plan' => $t->plan, 'fact' => $t->fact, 'status' => $t->status])->all(),
            'content' => $report->contentItems->map(fn ($c) => ['platform' => $c->platform, 'kind' => $c->kind, 'title' => $c->title, 'views' => $c->views, 'reactions' => $c->reactions, 'comments' => $c->comments, 'reposts' => $c->reposts, 'insight' => $c->insight])->all(),
            'business' => $report->business,
            'summary' => $report->summary,
            'plan_next' => $report->plan_next,
            'community' => $report->community,
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
