<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// канал или чат MAX, куда добавлен бот агентства
class MaxChat extends Model
{
    protected $fillable = ['chat_id', 'title', 'link', 'is_channel', 'participants_count', 'active'];

    protected $casts = ['is_channel' => 'boolean', 'active' => 'boolean'];
}
