<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReportTask extends Model
{
    protected $fillable = ['report_id','title','plan','fact','status','type','position'];
    public function report(): BelongsTo { return $this->belongsTo(Report::class); }
}
