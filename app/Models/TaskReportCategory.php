<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReportCategory extends Model
{
    use HasFactory;

    protected $table = 'task_report_categories';

    protected $fillable = [
        'task_id',
        'cat_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'task_id' => 'integer',
        'cat_id'  => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReportCategory::class, 'cat_id');
    }

    public function scopeByTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('cat_id', $categoryId);
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['task', 'category']);
    }
}