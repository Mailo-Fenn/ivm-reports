<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyStat extends Model
{
    protected $fillable = [
        'report_id',
        'platform',
        'label',
        'position',
        'subs',
        'views',
        'reach',
        'inter',
        'leads',
        'posts',
        'stories',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
