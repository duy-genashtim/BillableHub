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
    ];

    /**
     * Get the task associated with this relationship.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * Get the report category associated with this relationship.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ReportCategory::class, 'cat_id');
    }
}