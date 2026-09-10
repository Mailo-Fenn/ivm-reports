<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// сторис проекта, собранные по расписанию (в API они доступны только 24 часа)
class InstagramStory extends Model
{
    protected $fillable = ['project_id', 'story_id', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
