<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'timedoctor_version',
        'task_name',
        'slug',
        'user_list',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        // 'timedoctor_version' => 'integer',
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
        'user_list'      => 'json',
    ];

    protected $hidden = [
        'user_list',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($task) {
            if (empty($task->slug)) {
                $task->slug = $task->generateUniqueSlug();
            }
        });

        static::updating(function ($task) {
            if ($task->isDirty('task_name') && ! $task->isDirty('slug')) {
                $task->slug = $task->generateUniqueSlug();
            }
        });
    }

    public function generateUniqueSlug()
    {
        $slug         = Str::slug($this->task_name);
        $originalSlug = $slug;
        $count        = 2;

        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(WorklogsData::class, 'task_id');
    }

    public function reportCategories(): BelongsToMany
    {
        return $this->belongsToMany(ReportCategory::class, 'task_report_categories', 'task_id', 'cat_id');
    }

    /**
     * Get the report categories for this task.
     */
    public function taskReportCategories(): HasMany
    {
        return $this->hasMany(TaskReportCategory::class, 'task_id');
    }

    /**
     * Get the categories associated with this task through the junction table.
     */
    public function categories()
    {
        return $this->hasManyThrough(
            ReportCategory::class,
            TaskReportCategory::class,
            'task_id', // Foreign key on task_report_categories table
            'id',      // Foreign key on report_categories table
            'id',      // Local key on tasks table
            'cat_id'   // Local key on task_report_categories table
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNeedsSyncing($query, int $hoursThreshold = 24)
    {
        return $query->where(function ($query) use ($hoursThreshold) {
            $query->whereNull('last_synced_at')
                ->orWhere('last_synced_at', '<', now()->subHours($hoursThreshold));
        });
    }

    // public function scopeByVersion($query, int $version)
    // {
    //     return $query->where('timedoctor_version', $version);
    // }

    public function scopeBillable($query)
    {
        return $query->whereHas('reportCategories', function ($q) {
            $q->where('cat_name', 'like', '%billable%')
                ->orWhere('cat_name', 'Billable');
        });
    }

    public function scopeNonBillable($query)
    {
        return $query->whereDoesntHave('reportCategories', function ($q) {
            $q->where('cat_name', 'like', '%billable%')
                ->orWhere('cat_name', 'Billable');
        });
    }

    /**
     * Get user list as array.
     */
    public function getUserListArrayAttribute()
    {
        if (empty($this->user_list)) {
            return [];
        }

        try {
            return is_array($this->user_list)
            ? $this->user_list
            : (json_decode($this->user_list, true) ?: []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if task is billable based on category associations.
     */
    public function isBillable(): bool
    {
        return $this->reportCategories()
            ->whereHas('categoryType', function ($query) {
                $query->where('setting_value', 'like', '%billable%')
                    ->orWhere('setting_value', 'Billable');
            })
            ->exists();
    }

    /**
     * Get billable hours for this task in a date range.
     */
    public function getBillableHours($startDate = null, $endDate = null): float
    {
        $query = $this->worklogs()->where('is_active', true);

        if ($startDate && $endDate) {
            $query->whereBetween('start_time', [$startDate, $endDate]);
        }

        return round($query->sum('duration') / 3600, 2);
    }

    /**
     * Get total entries count for this task in a date range.
     */
    public function getEntriesCount($startDate = null, $endDate = null): int
    {
        $query = $this->worklogs()->where('is_active', true);

        if ($startDate && $endDate) {
            $query->whereBetween('start_time', [$startDate, $endDate]);
        }

        return $query->count();
    }

    /**
     * Scope to filter tasks by user ID (from user_list JSON).
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereJsonContains('user_list', ['userId' => $userId])
                ->orWhere('user_list', 'like', '%"userId":"' . $userId . '"%')
                ->orWhere('user_list', 'like', '%"userId":' . $userId . '%');
        });
    }

    /**
     * Scope to filter tasks by TimeDoctor ID (from user_list JSON).
     */
    public function scopeForTimeDoctorUser($query, $timeDoctorId)
    {
        return $query->where(function ($q) use ($timeDoctorId) {
            $q->whereJsonContains('user_list', ['tId' => $timeDoctorId])
                ->orWhere('user_list', 'like', '%"tId":"' . $timeDoctorId . '"%')
                ->orWhere('user_list', 'like', '%"tId":' . $timeDoctorId . '%');
        });
    }

    /**
     * Get tasks with category information for dashboard.
     */
    public function scopeWithCategoryInfo($query)
    {
        return $query->with(['reportCategories.categoryType']);
    }

    /**
     * Get performance metrics for this task.
     */
    public function getPerformanceMetrics($startDate = null, $endDate = null): array
    {
        $worklogs = $this->worklogs()->where('is_active', true);

        if ($startDate && $endDate) {
            $worklogs->whereBetween('start_time', [$startDate, $endDate]);
        }

        $worklogsCollection = $worklogs->get();
        $totalHours         = $worklogsCollection->sum('duration') / 3600;
        $entriesCount       = $worklogsCollection->count();

        return [
            'total_hours'      => round($totalHours, 2),
            'entries_count'    => $entriesCount,
            'average_duration' => $entriesCount > 0 ? round($totalHours / $entriesCount, 2) : 0,
            'is_billable'      => $this->isBillable(),
        ];
    }
}
// namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\HasMany;
// use Illuminate\Support\Str;

// class Task extends Model
// {
//     use HasFactory;

//     protected $fillable = [
//         'timedoctor_version',
//         'task_name',
//         'slug',
//         'user_list',
//         'is_active',
//         'last_synced_at',
//     ];

//     protected $casts = [
//         'timedoctor_version' => 'integer',
//         'is_active'          => 'boolean',
//         'last_synced_at'     => 'datetime',
//         'user_list'          => 'json',
//     ];

//     protected static function boot()
//     {
//         parent::boot();

//         static::creating(function ($task) {
//             if (empty($task->slug)) {
//                 $task->slug = $task->generateUniqueSlug();
//             }
//         });

//         static::updating(function ($task) {
//             if ($task->isDirty('task_name') && ! $task->isDirty('slug')) {
//                 $task->slug = $task->generateUniqueSlug();
//             }
//         });
//     }

//     public function generateUniqueSlug()
//     {
//         $slug         = Str::slug($this->task_name);
//         $originalSlug = $slug;
//         $count        = 2;

//         while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
//             $slug = "{$originalSlug}-{$count}";
//             $count++;
//         }

//         return $slug;
//     }

//     public function worklogs(): HasMany
//     {
//         return $this->hasMany(WorklogsData::class, 'task_id');
//     }

//     /**
//      * Get the report categories for this task.
//      */
//     public function reportCategories(): HasMany
//     {
//         return $this->hasMany(TaskReportCategory::class, 'task_id');
//     }

//     /**
//      * Get the categories associated with this task through the junction table.
//      */
//     public function categories()
//     {
//         return $this->hasManyThrough(
//             ReportCategory::class,
//             TaskReportCategory::class,
//             'task_id', // Foreign key on task_report_categories table
//             'id',      // Foreign key on report_categories table
//             'id',      // Local key on tasks table
//             'cat_id'   // Local key on task_report_categories table
//         );
//     }

//     public function scopeActive($query)
//     {
//         return $query->where('is_active', true);
//     }

//     public function scopeNeedsSyncing($query, int $hoursThreshold = 24)
//     {
//         return $query->where(function ($query) use ($hoursThreshold) {
//             $query->whereNull('last_synced_at')
//                 ->orWhere('last_synced_at', '<', now()->subHours($hoursThreshold));
//         });
//     }

//     public function scopeByVersion($query, int $version)
//     {
//         return $query->where('timedoctor_version', $version);
//     }

//     public function isBillable(): bool
//     {
//         return $this->reportCategories()
//             ->whereHas('category', function ($query) {
//                 $query->where('cat_name', 'like', '%billable%')
//                     ->orWhere('cat_name', 'Billable');
//             })
//             ->exists();
//     }
// }