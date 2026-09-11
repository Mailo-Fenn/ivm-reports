<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'description', 'client_period', 'manager', 'vk_group', 'vk_token', 'youtube_channel', 'instagram_username', 'instagram_user_id', 'instagram_token', 'instagram_token_expires_at', 'telegram_channel', 'tariff', 'is_active', 'share_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'vk_token' => 'encrypted',
        'instagram_token' => 'encrypted',
        'instagram_token_expires_at' => 'datetime',
    ];

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)
            ->orderByDesc('year')
            ->orderByDesc('month');
    }
}
