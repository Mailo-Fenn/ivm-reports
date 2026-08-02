<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContentItem extends Model
{
    protected $fillable = ['report_id','platform','kind','title','views','reactions','comments','reposts','insight','image','position'];
    public function report(): BelongsTo { return $this->belongsTo(Report::class); }
}
