<?php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyWorklogSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'iva_id',
        'report_category_id',
        'report_date',
        'total_duration',
        'entries_count',
        'category_type',
    ];

    protected $casts = [
        'report_date' => 'date:Y-m-d',
        'total_duration' => 'integer',
        'entries_count' => 'integer',
    ];

    // Relationships
    public function ivaUser(): BelongsTo
    {
        return $this->belongsTo(IvaUser::class, 'iva_id');
    }

    public function reportCategory(): BelongsTo
    {
        return $this->belongsTo(ReportCategory::class, 'report_category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereHas('ivaUser', function ($q) {
            $q->where('is_active', true);
        });
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('report_date', [$startDate, $endDate]);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('iva_id', $userId);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('report_category_id', $categoryId);
    }

    public function scopeByCategoryType($query, $categoryType)
    {
        return $query->where('category_type', $categoryType);
    }

    public function scopeByRegion($query, $regionId)
    {
        return $query->whereHas('ivaUser', function ($q) use ($regionId) {
            $q->where('region_id', $regionId);
        });
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['ivaUser', 'reportCategory']);
    }

    // Accessors
    public function getDurationHoursAttribute()
    {
        return round($this->total_duration / 3600, 2);
    }

    public function getFormattedDurationAttribute()
    {
        $hours = floor($this->total_duration / 3600);
        $minutes = floor(($this->total_duration % 3600) / 60);

        return sprintf('%dh %dm', $hours, $minutes);
    }

    // Static methods for aggregation
    public static function getTotalHoursByUser($userId, $startDate = null, $endDate = null, $categoryType = null)
    {
        $query = static::byUser($userId);

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        if ($categoryType) {
            $query->byCategoryType($categoryType);
        }

        return round($query->sum('total_duration') / 3600, 2);
    }

    public static function getTotalHoursByRegion($regionId, $startDate = null, $endDate = null, $categoryType = null)
    {
        $query = static::byRegion($regionId);

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        if ($categoryType) {
            $query->byCategoryType($categoryType);
        }

        return round($query->sum('total_duration') / 3600, 2);
    }

    public static function getPerformanceSummary($userId, $startDate, $endDate)
    {
        $summaries = static::byUser($userId)
            ->byDateRange($startDate, $endDate)
            ->get();

        $totalHours = $summaries->sum('total_duration') / 3600;
        $billableHours = $summaries->where('category_type', 'billable')->sum('total_duration') / 3600;
        $workingDays = $summaries->groupBy('report_date')->count();

        return [
            'total_hours' => round($totalHours, 2),
            'billable_hours' => round($billableHours, 2),
            'non_billable_hours' => round($totalHours - $billableHours, 2),
            'working_days' => $workingDays,
            'average_daily_hours' => $workingDays > 0 ? round($totalHours / $workingDays, 2) : 0,
            'entries_count' => $summaries->sum('entries_count'),
        ];
    }

    public static function getCategoryBreakdown($userId, $startDate, $endDate)
    {
        return static::byUser($userId)
            ->byDateRange($startDate, $endDate)
            ->with('reportCategory')
            ->get()
            ->groupBy('report_category_id')
            ->map(function ($categoryData) {
                $category = $categoryData->first()->reportCategory;
                $totalHours = $categoryData->sum('total_duration') / 3600;

                return [
                    'category_id' => $category->id,
                    'category_name' => $category->cat_name,
                    'category_type' => $categoryData->first()->category_type,
                    'total_hours' => round($totalHours, 2),
                    'entries_count' => $categoryData->sum('entries_count'),
                ];
            })
            ->sortByDesc('total_hours')
            ->values();
    }
}