<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    // values may hold API tokens — always encrypted at rest
    protected $casts = ['value' => 'encrypted'];

    public static function get(string $key): ?string
    {
        return static::where('key', $key)->first()?->value;
    }

    public static function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            static::where('key', $key)->delete();
        } else {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
