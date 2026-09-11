<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// публикация контент-плана: текст + файлы, площадки и время выхода
class Post extends Model
{
    public const STATUSES = ['draft', 'scheduled', 'publishing', 'published', 'partial', 'failed'];

    protected $fillable = ['project_id', 'text', 'media', 'platforms', 'scheduled_at', 'status'];

    protected $casts = [
        'media' => 'array',
        'platforms' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(PostPublication::class);
    }

    // файлы публикации с абсолютными путями и публичными адресами
    public function mediaFiles(): array
    {
        return array_values(array_map(fn ($m) => [
            'path' => $m['path'],
            'type' => $m['type'] ?? (str_starts_with((string) ($m['mime'] ?? ''), 'video') ? 'video' : 'image'),
            'name' => $m['name'] ?? basename($m['path']),
            'abs' => storage_path('app/public/'.$m['path']),
            'url' => url('/storage/'.$m['path']),
        ], array_filter($this->media ?? [], fn ($m) => !empty($m['path']))));
    }

    // итоговый статус по результатам отправки в площадки
    public function refreshStatus(): void
    {
        $pubs = $this->publications()->get();
        $published = $pubs->where('status', 'published')->count();
        $failed = $pubs->where('status', 'failed')->count();
        $total = count($this->platforms ?? []);

        $this->status = match (true) {
            $total > 0 && $published === $total => 'published',
            $published > 0 => 'partial',
            $failed > 0 => 'failed',
            default => $this->status === 'publishing' ? 'scheduled' : $this->status,
        };
        $this->save();
    }
}
