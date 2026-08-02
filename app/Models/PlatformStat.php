<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PlatformStat extends Model
{
    protected $fillable = ['report_id','platform','subs','views','reach','inter','leads','posts','stories','is_enabled'];
    protected $appends = ['er'];
    protected $casts = [
        'is_enabled' => 'boolean',
    ];
    public function report(): BelongsTo { return $this->belongsTo(Report::class); }
    public function getErAttribute(): float { return $this->subs > 0 ? round($this->inter / $this->subs * 100, 1) : 0; }
}
