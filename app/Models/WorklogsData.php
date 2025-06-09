<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorklogsData extends Model
{
    use HasFactory;

    protected $table = 'worklogs_data';

    protected $fillable = [
        'iva_id',
        'timedoctor_project_id',
        'timedoctor_task_id',
        'project_id',
        'task_id',
        'work_mode',
        'start_time',
        'end_time',
        'duration',
        'device_id',
        'comment',
        'api_type',
        'timedoctor_worklog_id',
        'is_active',
        'update_comment',
        'timedoctor_version',
        'tm_user_id',
    ];

    protected $casts = [
        'start_time'         => 'datetime',
        'end_time'           => 'datetime',
        'duration'           => 'integer',
        'is_active'          => 'boolean',
        'timedoctor_version' => 'integer',
    ];

    public function ivaUser(): BelongsTo
    {
        return $this->belongsTo(IvaUser::class, 'iva_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByApiType($query, string $apiType)
    {
        return $query->where('api_type', $apiType);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_time', [$startDate, $endDate]);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('iva_id', $userId);
    }
}