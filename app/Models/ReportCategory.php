<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCategory extends Model
{
    use HasFactory;

    protected $table = 'report_categories';

    protected $fillable = [
        'cat_name',
        'cat_description',
        'is_active',
        'category_order',
        'category_type',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'category_order' => 'integer',
    ];

    /**
     * Get the category type for this report category.
     */
    public function categoryType(): BelongsTo
    {
        return $this->belongsTo(ConfigurationSetting::class, 'category_type');
    }

    /**
     * Get the task categories (junction table).
     */
    public function taskCategories(): HasMany
    {
        return $this->hasMany(TaskReportCategory::class, 'cat_id');
    }

    /**
     * Get the tasks associated with this category through the junction table.
     */
    public function tasks()
    {
        return $this->hasManyThrough(
            Task::class,
            TaskReportCategory::class,
            'cat_id', // Foreign key on task_report_categories table
            'id',     // Foreign key on tasks table
            'id',     // Local key on report_categories table
            'task_id' // Local key on task_report_categories table
        );
    }

    /**
     * Scope a query to only include active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for billable categories.
     */
    public function scopeBillable($query)
    {
        return $query->where('cat_name', 'like', '%billable%');
    }

    /**
     * Get the next available order number
     */
    public static function getNextOrder(): int
    {
        return static::max('category_order') + 1;
    }

    /**
     * Check if category can be deleted (no tasks assigned)
     */
    public function canBeDeleted(): bool
    {
        return $this->taskCategories()->count() === 0;
    }

    protected static function booted()
    {
        // Default sorting by category_order ASC
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('category_order', 'asc');
        });
    }
}