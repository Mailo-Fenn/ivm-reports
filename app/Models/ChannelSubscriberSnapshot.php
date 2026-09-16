<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// ежедневный снимок подписчиков площадки без истории (сейчас — MAX)
class ChannelSubscriberSnapshot extends Model
{
    protected $fillable = ['project_id', 'platform', 'taken_on', 'subscribers'];

    protected $casts = ['taken_on' => 'date'];

    // снимок за день: обновляет существующий или создаёт новый (дата сравнивается без времени)
    public static function record(int $projectId, string $platform, string $date, int $subscribers): void
    {
        $row = static::where('project_id', $projectId)->where('platform', $platform)->whereDate('taken_on', $date)->first()
            ?? new static(['project_id' => $projectId, 'platform' => $platform, 'taken_on' => $date]);
        $row->subscribers = $subscribers;
        $row->save();
    }
}
