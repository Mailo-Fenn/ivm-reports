<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Report;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    private array $platforms = ['vk', 'ig', 'max', 'yt', 'tg'];
    private array $names = ['vk' => 'ВКонтакте', 'ig' => 'Инстаграм', 'max' => 'Макс', 'yt' => 'YouTube', 'tg' => 'Телеграм'];

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $report = $project->reports()->firstOrCreate($data);

        // legacy weekly seed (kept from previous site)
        if ($report->weeklyStats()->count() === 0) {
            foreach ($this->platforms as $platform) {
                foreach (range(1, 4) as $i) {
                    $report->weeklyStats()->create([
                        'platform' => $platform,
                        'label' => "Неделя $i",
                        'position' => $i,
                    ]);
                }
            }
        }
        
        // задачи «план / факт» и чек-лист — по тарифу проекта (config/tariffs.php)
        if ($report->tasks()->count() === 0) {
            $tariff = config('tariffs.list.'.$project->tariff) ?: config('tariffs.list.'.config('tariffs.default'));
            $pos = 0;
            foreach ($tariff['plan_fact'] ?? [] as [$title, $plan]) {
                $report->tasks()->create(['title' => $title, 'plan' => $plan, 'fact' => '', 'status' => 'в работе', 'type' => 'plan_fact', 'position' => $pos++]);
            }
            foreach ($tariff['checklist'] ?? [] as $title) {
                $report->tasks()->create(['title' => $title, 'plan' => '', 'fact' => '', 'status' => 'в работе', 'type' => 'check', 'position' => $pos++]);
            }
        }

        return redirect()->route('reports.show', $report);
    }

    public function show(Report $report)
    {
        $report->load([
            'project',
            'tasks',
            'contentItems',
            'weeklyStats'
        ]);

        $prev = $report->previousReport();

        $prev?->load('weeklyStats');

        $project = $report->project;

        $reports = $project->reports->map(fn ($report) => [
            'id'           => $report->id,
            'year'         => $report->year,
            'month'        => $report->month,
            'period_label' => $report->period_label,
            'totals'       => $report->totals,
        ]);

        // series: last up-to-6 months of this project (ascending) for charts
        $history = Report::where('project_id', $report->project_id)
            ->where(fn ($q) => $q->where('year', '<', $report->year)
                ->orWhere(fn ($q2) => $q2->where('year', $report->year)->where('month', '<=', $report->month)))
            ->with('weeklyStats')
            ->orderByDesc('year')->orderByDesc('month')->limit(6)->get()->reverse()->values();

        $shortMonth = ['', 'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];

        $series = $history->map(function ($r) use ($shortMonth) {
            $row = ['k' => $shortMonth[$r->month]];

            foreach ($this->platforms as $p) {
                $stats = $r->platform_totals[$p] ?? [];

                $row[$p] = [
                    'subs' => $stats['subs'] ?? 0,
                    'views' => $stats['views'] ?? 0,
                    'reach' => $stats['reach'] ?? 0,
                    'inter' => $stats['inter'] ?? 0,
                    'er' => $stats['er'] ?? 0,
                    'leads' => $stats['leads'] ?? 0,
                    'posts' => $stats['posts'] ?? 0,
                    'stories' => $stats['stories'] ?? 0,
                ];
            }

            return $row;
        });

        $platMap = fn ($rep) => collect($this->platforms)
        ->mapWithKeys(function ($p) use ($rep) {

            $stats = $rep?->platform_totals[$p] ?? null;

            return [
                $p => [
                    'is_enabled' => $rep ? $rep->platformEnabled($p) : true,
                    'subs' => $stats['subs'] ?? 0,
                    'views' => $stats['views'] ?? 0,
                    'reach' => $stats['reach'] ?? 0,
                    'inter' => $stats['inter'] ?? 0,
                    'er' => $stats['er'] ?? 0,
                    'leads' => $stats['leads'] ?? 0,
                    'posts' => $stats['posts'] ?? 0,
                    'stories' => $stats['stories'] ?? 0,
                ]
            ];
        });

        return Inertia::render('Reports/Show', [
            'project' => ['id' => $report->project->id, 'name' => $report->project->name, 'color' => $report->project->color, 'vk_group' => $report->project->vk_group, 'youtube_channel' => $report->project->youtube_channel, 'instagram_connected' => (bool) $report->project->instagram_token, 'telegram_channel' => $report->project->telegram_channel],
            'report' => [
                'id' => $report->id, 'year' => $report->year, 'month' => $report->month,
                'period_label' => $report->period_label,
                'summary' => $report->summary, 'plan_next' => $report->plan_next,
                'community' => self::communityByPlatform($report->community), 'business' => $report->business,
                'metric_notes' => $report->metric_notes,
            ],
            'reports' => $reports,
            'platformNames' => $this->names,
            'current' => $platMap($report),
            'previous' => ['label' => $prev?->period_label, 'stats' => $prev ? $platMap($prev) : null],
            'series' => $series,
            'tasks' => $report->tasks->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'plan' => $t->plan, 'fact' => $t->fact, 'status' => $t->status, 'type' => $t->type]),
            'content' => $report->contentItems->map(fn ($c) => ['id' => $c->id, 'platform' => $c->platform, 'kind' => $c->kind, 'title' => $c->title, 'views' => $c->views, 'reactions' => $c->reactions, 'comments' => $c->comments, 'reposts' => $c->reposts, 'insight' => $c->insight, 'image' => $c->image]),
            'weeks' => $report->weeklyStats->map(fn ($w) => [
                'id' => $w->id,
                'platform' => $w->platform,
                'position' => $w->position,
                'label' => $w->label,
                'subs' => $w->subs,
                'views' => $w->views,
                'reach' => $w->reach,
                'inter' => $w->inter,
                'leads' => $w->leads,
                'posts' => $w->posts,
                'stories' => $w->stories,
            ]),
        ]);
    }

    public function update(Request $request, Report $report)
    {
        $data = $request->validate([
            'summary' => 'nullable|string',
            'plan_next' => 'nullable|string',
            'community' => 'nullable|array',
            'community.*' => 'nullable|array',
            'community.*.*.image' => 'nullable|string|max:255',
            'community.*.*.caption' => 'nullable|string',
            'metric_notes' => 'nullable|array',
            'metric_notes.*' => 'array',
            'metric_notes.*.*' => 'array',
            'metric_notes.*.*.*' => 'nullable|string',
            'business' => 'nullable|array',
            'business.ad_clicks' => 'nullable',
            'business.ad_subs' => 'nullable',
            'business.ad_views' => 'nullable',
            'business.ad_budget' => 'nullable',
            'tasks' => 'array',
            'tasks.*.title' => 'required|string|max:255',
            'tasks.*.plan' => 'nullable|string|max:64',
            'tasks.*.fact' => 'nullable|string|max:64',
            'tasks.*.status' => 'nullable|string|max:64',
            'content' => 'array',
            'content.*.platform' => 'required|string|max:16',
            'content.*.kind' => 'nullable|string|max:16',
            'content.*.title' => 'required|string|max:255',
            'content.*.views' => 'integer|min:0',
            'content.*.reactions' => 'integer|min:0',
            'content.*.comments' => 'integer|min:0',
            'content.*.reposts' => 'integer|min:0',
            'content.*.insight' => 'nullable|string',
            'content.*.image' => 'nullable|string|max:255',
            'weeks' => 'array',
            'weeks.*.id' => 'nullable|integer',
            'weeks.*.label' => 'required_with:weeks|string|max:255',
            'weeks.*.subs' => 'nullable|integer',
            'weeks.*.views' => 'nullable|integer|min:0',
            'weeks.*.reach' => 'nullable|integer|min:0',
            'weeks.*.inter' => 'nullable|integer|min:0',
            'weeks.*.leads' => 'nullable|integer|min:0',
            'weeks.*.posts' => 'nullable|integer|min:0',
            'weeks.*.stories' => 'nullable|integer|min:0',
            'weeks.*.platform' => 'required|string|in:vk,ig,max,yt,tg',
            'weeks.*.position' => 'required|integer|min:1|max:4',
            'tasks.*.type' => 'required|string|in:plan_fact,check',
        ]);

        // выводы по площадкам: оставляем только известные площадки/метрики и непустые строки
        $notes = [];
        foreach (($data['metric_notes'] ?? []) as $plat => $metrics) {
            if (!in_array($plat, $this->platforms, true) || !is_array($metrics)) {
                continue;
            }
            foreach ($metrics as $mk => $list) {
                if (!in_array($mk, ['subs', 'views', 'inter'], true) || !is_array($list)) {
                    continue;
                }
                $vals = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $list), fn ($v) => $v !== ''));
                if ($vals) {
                    $notes[$plat][$mk] = $vals;
                }
            }
        }

        $report->update([
            'summary' => $data['summary'] ?? null,
            'plan_next' => $data['plan_next'] ?? null,
            'metric_notes' => $notes ?: null,
            'community' => $this->cleanCommunity($data['community'] ?? []),
            'business' => array_intersect_key($data['business'] ?? [], array_flip(['ad_clicks', 'ad_subs', 'ad_views', 'ad_budget'])) ?: null,
        ]);

        $report->tasks()->delete();
        foreach ($data['tasks'] ?? [] as $i => $t) {
            $report->tasks()->create(['title' => $t['title'], 'plan' => $t['plan'] ?? '', 'fact' => $t['fact'] ?? '', 'status' => $t['status'] ?? 'выполнено', 'type' => $t['type'] ?? 'plan_fact', 'position' => $i]);
        }

        $report->contentItems()->delete();

        foreach ($data['content'] ?? [] as $i => $c) {
            $isStory = ($c['kind'] ?? 'post') === 'story';
            $report->contentItems()->create([
                'platform'  => $c['platform'],
                'kind'      => $c['kind'] ?? 'post',
                'title'     => $c['title'],
                'views'     => $c['views'] ?? 0,
                'reactions' => $c['reactions'] ?? 0,
                // у сторис комментариев и репостов не бывает
                'comments'  => $isStory ? 0 : ($c['comments'] ?? 0),
                'reposts'   => $isStory ? 0 : ($c['reposts'] ?? 0),
                'insight'   => $c['insight'] ?? null,
                'image'     => $c['image'] ?? null,
                'position'  => $i,
            ]);
        }

        if (isset($data['weeks'])) {

            $keep = [];

            foreach ($data['weeks'] as $w) {

                $payload = [
                    'platform' => $w['platform'],

                    'label' => $w['label'],

                    'position' => $w['position'],

                    'subs' => $w['subs'] ?? 0,
                    'views' => $w['views'] ?? 0,
                    'reach' => $w['reach'] ?? 0,
                    'inter' => $w['inter'] ?? 0,
                    'leads' => $w['leads'] ?? 0,
                    'posts' => $w['posts'] ?? 0,
                    'stories' => $w['stories'] ?? 0,
                ];

                if (!empty($w['id'])) {

                    $report
                        ->weeklyStats()
                        ->where('id', $w['id'])
                        ->update($payload);

                    $keep[] = $w['id'];

                } else {

                    $keep[] = $report
                        ->weeklyStats()
                        ->create($payload)
                        ->id;
                }
            }

            $report
                ->weeklyStats()
                ->whereNotIn('id', $keep)
                ->delete();
        }
        
        $report->syncPlatformStats();

        return redirect()->route('reports.show', $report)->with('success', 'Отчёт сохранён');
    }

    // «Работа с сообществом» по площадкам: {vk: [{image, caption}], ig: [...]};
    // старые отчёты хранили плоский список — считаем его записями ВК
    public static function communityByPlatform($community): array
    {
        if (!is_array($community) || !$community) {
            return [];
        }
        if (array_is_list($community)) {
            return ['vk' => array_values($community)];
        }

        return $community;
    }

    // оставляем только известные площадки и непустые строки (картинка или подпись)
    private function cleanCommunity(array $community): ?array
    {
        $out = [];
        foreach ($community as $platform => $items) {
            if (!in_array($platform, $this->platforms, true) || !is_array($items)) {
                continue;
            }
            $kept = array_values(array_filter($items, fn ($i) => is_array($i) && (!empty($i['image']) || trim($i['caption'] ?? '') !== '')));
            if ($kept) {
                $out[$platform] = array_map(fn ($i) => ['image' => $i['image'] ?? null, 'caption' => $i['caption'] ?? ''], $kept);
            }
        }

        return $out ?: null;
    }

    public function destroy(Report $report)
    {
        $pid = $report->project_id;
        $report->delete();
        return redirect()->route('projects.show', $pid)->with('success', 'Отчёт удалён');
    }
}
