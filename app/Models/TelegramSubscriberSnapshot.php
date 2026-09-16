<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ежедневный снимок числа подписчиков канала Telegram проекта
class TelegramSubscriberSnapshot extends Model
{
    protected $fillable = ['project_id', 'taken_on', 'subscribers'];

    protected $casts = ['taken_on' => 'date'];

    // снимок за день без дублей: дата сравнивается без времени
    public static function record(int $projectId, string $date, int $subscribers): void
    {
        $row = static::where('project_id', $projectId)->whereDate('taken_on', $date)->first()
            ?? new static(['project_id' => $projectId, 'taken_on' => $date]);
        $row->subscribers = $subscribers;
        $row->save();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
