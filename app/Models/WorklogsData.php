<?php
namespace App\Models;

use Carbon\Carbon;
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

    // Relationships
    public function ivaUser(): BelongsTo
    {
        return $this->belongsTo(IvaUser::class, 'iva_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    // Scopes
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

    public function scopeBillable($query)
    {
        return $query->where('api_type', 'timedoctor');
    }

    public function scopeNonBillable($query)
    {
        return $query->where('api_type', 'manual');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('start_time', Carbon::today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('start_time', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('start_time', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        ]);
    }

    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['project', 'task', 'ivaUser']);
    }

    // Accessors
    public function getDurationHoursAttribute()
    {
        return round($this->duration / 3600, 2);
    }

    public function getFormattedDurationAttribute()
    {
        $hours   = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);

        return sprintf('%dh %dm', $hours, $minutes);
    }

    public function getDateAttribute()
    {
        return $this->start_time ? $this->start_time->toDateString() : null;
    }

    public function getDayNameAttribute()
    {
        return $this->start_time ? $this->start_time->format('l') : null;
    }

    public function getIsBillableAttribute()
    {
        return $this->api_type === 'timedoctor';
    }

    // Mutators
    public function setStartTimeAttribute($value)
    {
        $this->attributes['start_time'] = Carbon::parse($value);
    }

    public function setEndTimeAttribute($value)
    {
        $this->attributes['end_time'] = Carbon::parse($value);
    }

    // Methods
    public function calculateDuration()
    {
        if ($this->start_time && $this->end_time) {
            $this->duration = $this->end_time->diffInSeconds($this->start_time);
            return $this->duration;
        }
        return 0;
    }

    public function isOverlapping($startTime, $endTime, $excludeId = null)
    {
        $query = static::where('iva_id', $this->iva_id)
            ->where('is_active', true)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($subQ) use ($startTime, $endTime) {
                        $subQ->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function getCategoryInfo()
    {
        if (! $this->task) {
            return null;
        }

        $category = $this->task->reportCategories()->first();
        return $category ? [
            'id'          => $category->id,
            'name'        => $category->cat_name,
            'type'        => $category->categoryType?->setting_value,
            'is_billable' => $this->is_billable,
        ] : null;
    }

    /**
     * Get worklogs grouped by date for dashboard.
     */
    public static function getGroupedByDate($userId, $startDate, $endDate)
    {
        return static::byUser($userId)
            ->active()
            ->byDateRange($startDate, $endDate)
            ->get()
            ->groupBy(function ($worklog) {
                return $worklog->start_time->toDateString();
            });
    }

    /**
     * Get hourly breakdown for a specific date.
     */
    public static function getHourlyBreakdown($userId, $date)
    {
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay   = Carbon::parse($date)->endOfDay();

        return static::byUser($userId)
            ->active()
            ->byDateRange($startOfDay, $endOfDay)
            ->get()
            ->groupBy(function ($worklog) {
                return $worklog->start_time->format('H');
            });
    }

    /**
     * Get performance summary for user in date range.
     */
    public static function getPerformanceSummary($userId, $startDate, $endDate)
    {
        $worklogs = static::byUser($userId)
            ->active()
            ->byDateRange($startDate, $endDate)
            ->get();

        $totalHours    = $worklogs->sum('duration') / 3600;
        $billableHours = $worklogs->where('api_type', 'timedoctor')->sum('duration') / 3600;
        $workingDays   = $worklogs->groupBy('date')->count();

        return [
            'total_hours'           => round($totalHours, 2),
            'billable_hours'        => round($billableHours, 2),
            'non_billable_hours'    => round($totalHours - $billableHours, 2),
            'working_days'          => $workingDays,
            'average_daily_hours'   => $workingDays > 0 ? round($totalHours / $workingDays, 2) : 0,
            'efficiency_percentage' => $totalHours > 0 ? round(($billableHours / $totalHours) * 100, 1) : 0,
            'entries_count'         => $worklogs->count(),
        ];
    }

    /**
     * Get top projects by hours for user.
     */
    public static function getTopProjects($userId, $startDate, $endDate, $limit = 10)
    {
        return static::byUser($userId)
            ->active()
            ->byDateRange($startDate, $endDate)
            ->with('project')
            ->get()
            ->groupBy('project_id')
            ->map(function ($projectWorklogs) {
                $totalHours = $projectWorklogs->sum('duration') / 3600;
                $project    = $projectWorklogs->first()->project;

                return [
                    'project_id'    => $project?->id,
                    'project_name'  => $project?->project_name ?? 'No Project',
                    'total_hours'   => round($totalHours, 2),
                    'entries_count' => $projectWorklogs->count(),
                ];
            })
            ->sortByDesc('total_hours')
            ->take($limit)
            ->values();
    }

    /**
     * Get top tasks by hours for user.
     */
    public static function getTopTasks($userId, $startDate, $endDate, $limit = 10)
    {
        return static::byUser($userId)
            ->active()
            ->byDateRange($startDate, $endDate)
            ->with('task')
            ->get()
            ->groupBy('task_id')
            ->map(function ($taskWorklogs) {
                $totalHours = $taskWorklogs->sum('duration') / 3600;
                $task       = $taskWorklogs->first()->task;

                return [
                    'task_id'       => $task?->id,
                    'task_name'     => $task?->task_name ?? 'No Task',
                    'total_hours'   => round($totalHours, 2),
                    'entries_count' => $taskWorklogs->count(),
                    'is_billable'   => $taskWorklogs->first()->is_billable,
                ];
            })
            ->sortByDesc('total_hours')
            ->take($limit)
            ->values();
    }
}
// namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsTo;

// class WorklogsData extends Model
// {
//     use HasFactory;

//     protected $table = 'worklogs_data';

//     protected $fillable = [
//         'iva_id',
//         'timedoctor_project_id',
//         'timedoctor_task_id',
//         'project_id',
//         'task_id',
//         'work_mode',
//         'start_time',
//         'end_time',
//         'duration',
//         'device_id',
//         'comment',
//         'api_type',
//         'timedoctor_worklog_id',
//         'is_active',
//         'update_comment',
//         'timedoctor_version',
//         'tm_user_id',
//     ];

//     protected $casts = [
//         'start_time'         => 'datetime',
//         'end_time'           => 'datetime',
//         'duration'           => 'integer',
//         'is_active'          => 'boolean',
//         'timedoctor_version' => 'integer',
//     ];

//     public function ivaUser(): BelongsTo
//     {
//         return $this->belongsTo(IvaUser::class, 'iva_id');
//     }

//     public function project(): BelongsTo
//     {
//         return $this->belongsTo(Project::class);
//     }

//     public function task(): BelongsTo
//     {
//         return $this->belongsTo(Task::class);
//     }

//     public function scopeActive($query)
//     {
//         return $query->where('is_active', true);
//     }

//     public function scopeByApiType($query, string $apiType)
//     {
//         return $query->where('api_type', $apiType);
//     }

//     public function scopeByDateRange($query, $startDate, $endDate)
//     {
//         return $query->whereBetween('start_time', [$startDate, $endDate]);
//     }

//     public function scopeByUser($query, $userId)
//     {
//         return $query->where('iva_id', $userId);
//     }
// }