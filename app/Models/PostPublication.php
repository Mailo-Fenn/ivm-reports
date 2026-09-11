<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// результат отправки публикации в одну площадку
class PostPublication extends Model
{
    protected $fillable = ['post_id', 'platform', 'status', 'external_id', 'url', 'error', 'attempts', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
