<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    public const PLATFORMS = ['vk', 'ig', 'max', 'yt'];

    protected $fillable = ['project_id','year','month','summary','plan_next','community','business','metric_notes'];
    protected $casts = ['business' => 'array', 'community' => 'array', 'metric_notes' => 'array'];
    protected $appends = [
        'period_label',
        'totals',
        'platform_totals'
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function weeklyStats(): HasMany
    {
        return $this->hasMany(WeeklyStat::class)
            ->orderBy('position');
    }
    public function platformStats(): HasMany { return $this->hasMany(PlatformStat::class); }
    public function tasks(): HasMany { return $this->hasMany(ReportTask::class)->orderBy('position'); }
    public function contentItems(): HasMany { return $this->hasMany(ContentItem::class)->orderBy('position'); }

    public function getPeriodLabelAttribute(): string
    {
        $m = ['','Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
        return trim(($m[$this->month] ?? '').' '.$this->year);
    }

    // previous month's report of the same project (for month-over-month)
    public function previousReport(): ?Report
    {
        return static::where('project_id', $this->project_id)
            ->where(fn ($q) => $q->where('year', '<', $this->year)
                ->orWhere(fn ($q2) => $q2->where('year', $this->year)->where('month', '<', $this->month)))
            ->orderByDesc('year')->orderByDesc('month')->first();
    }

    // totals: prefer platform stats, fall back to weekly stats (legacy)
    public function getTotalsAttribute(): array
    {
        $w = $this->relationLoaded('weeklyStats')
            ? $this->weeklyStats
            : $this->weeklyStats()->get();


        $sum = fn ($k) => (int) $w->sum($k);


        $reach = $sum('reach');
        $inter = $sum('inter');


        return [
            'subs' => $sum('subs'),
            'views' => $sum('views'),
            'reach' => $reach,
            'inter' => $inter,
            'leads' => $sum('leads'),
            'posts' => $sum('posts'),
            'stories' => $sum('stories'),

            'er' => $reach
                ? round($inter / $reach * 100, 1)
                : 0,
        ];
    }

    public function getPlatformTotalsAttribute(): array
    {
        $weeks = $this->weeklyStats()
            ->get()
            ->groupBy('platform');

        $result = [];

        foreach ($weeks as $platform => $items) {

            $reach = $items->sum('reach');
            $inter = $items->sum('inter');


            $result[$platform] = [
                'subs' => (int)$items->sum('subs'),
                'views' => (int)$items->sum('views'),
                'reach' => (int)$reach,
                'inter' => (int)$inter,
                'leads' => (int)$items->sum('leads'),
                'posts' => (int)$items->sum('posts'),
                'stories' => (int)$items->sum('stories'),

                'er' => $reach
                    ? round($inter / $reach * 100, 1)
                    : 0,
            ];
        }

        // у площадок без понедельной статистики берём готовые месячные итоги —
        // иначе прошлые месяцы выглядят нулевыми и динамика завышается
        $stats = $this->relationLoaded('platformStats') ? $this->platformStats : $this->platformStats()->get();
        foreach ($stats as $ps) {
            if (isset($result[$ps->platform])) {
                continue;
            }
            $result[$ps->platform] = [
                'subs' => (int)$ps->subs,
                'views' => (int)$ps->views,
                'reach' => (int)$ps->reach,
                'inter' => (int)$ps->inter,
                'leads' => (int)$ps->leads,
                'posts' => (int)$ps->posts,
                'stories' => (int)$ps->stories,
                'er' => $ps->reach ? round($ps->inter / $ps->reach * 100, 1) : 0,
            ];
        }

        return $result;
    }

    // YouTube показываем только там, где он реально ведётся: у проекта указан канал
    // либо по площадке уже есть цифры; остальные площадки включены всегда (как раньше)
    public function platformEnabled(string $platform): bool
    {
        if ($platform !== 'yt') {
            return true;
        }
        if ($this->project?->youtube_channel) {
            return true;
        }
        $stats = $this->relationLoaded('platformStats') ? $this->platformStats : $this->platformStats()->get();
        $ps = $stats->firstWhere('platform', 'yt');

        return $ps ? ($ps->subs || $ps->views || $ps->inter || $ps->posts) : false;
    }

    public function weeklyStatsByPlatform()
    {
        return $this->weeklyStats
            ->groupBy('platform');
    }

    public function syncPlatformStats(): void
    {
        foreach (self::PLATFORMS as $platform) {

            $weekly = $this->weeklyStats()
                ->where('platform', $platform)
                ->get();

            $this->platformStats()->updateOrCreate(
                ['platform' => $platform],
                [
                    'subs' => $weekly->sum('subs'),
                    'views'   => $weekly->sum('views'),
                    'reach'   => $weekly->sum('reach'),
                    'inter' => $weekly->sum('inter'),
                    'leads'   => $weekly->sum('leads'),
                    'posts'   => $weekly->sum('posts'),
                    'stories' => $weekly->sum('stories'),
                ]
            );
        }
    }
}
