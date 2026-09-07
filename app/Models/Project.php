<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'description', 'client_period', 'manager', 'vk_group', 'vk_token', 'youtube_channel', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'vk_token' => 'encrypted',
    ];

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)
            ->orderByDesc('year')
            ->orderByDesc('month');
    }
}
