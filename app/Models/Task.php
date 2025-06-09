<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'timedoctor_version',
        'task_name',
        'slug',
        'user_list',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'timedoctor_version' => 'integer',
        'is_active'          => 'boolean',
        'last_synced_at'     => 'datetime',
        'user_list'          => 'json',
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

    /**
     * Get the report categories for this task.
     */
    public function reportCategories(): HasMany
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

    public function scopeByVersion($query, int $version)
    {
        return $query->where('timedoctor_version', $version);
    }

    public function isBillable(): bool
    {
        return $this->reportCategories()
            ->whereHas('category', function ($query) {
                $query->where('cat_name', 'like', '%billable%')
                    ->orWhere('cat_name', 'Billable');
            })
            ->exists();
    }
}
