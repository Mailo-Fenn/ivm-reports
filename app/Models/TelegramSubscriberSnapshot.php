<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ежедневный снимок числа подписчиков канала Telegram проекта
class TelegramSubscriberSnapshot extends Model
{
    protected $fillable = ['project_id', 'taken_on', 'subscribers'];

    protected $casts = ['taken_on' => 'date'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
