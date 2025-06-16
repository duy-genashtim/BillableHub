<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvaUserCustomize extends Model
{
    use HasFactory;

    protected $table = 'iva_user_customize';

    protected $fillable = [
        'iva_user_id',
        'setting_id',
        'start_date',
        'end_date',
        'custom_value',
    ];

    protected $casts = [
        'iva_user_id' => 'integer',
        'setting_id'  => 'integer',
        'start_date'  => 'date',
        'end_date'    => 'date',
    ];

    /**
     * Get the user that owns the customization.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(IvaUser::class, 'iva_user_id');
    }

    public function ivaUser(): BelongsTo
    {
        return $this->belongsTo(IvaUser::class, 'iva_user_id');
    }

    /**
     * Get the configuration setting for this customization.
     */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(ConfigurationSetting::class, 'setting_id');
    }

    /**
     * Get formatted start date.
     */
    public function getFormattedStartDateAttribute()
    {
        return $this->start_date ? $this->start_date->format('Y-m-d') : null;
    }

    /**
     * Get formatted end date.
     */
    public function getFormattedEndDateAttribute()
    {
        return $this->end_date ? $this->end_date->format('Y-m-d') : null;
    }

    /**
     * Scope to filter active customizations based on date range
     */
    public function scopeActive($query, $date = null)
    {
        $date = $date ?: now()->toDateString();

        return $query->where(function ($q) use ($date) {
            $q->where(function ($subQ) use ($date) {
                // Start date is null or before/equal to current date
                $subQ->whereNull('start_date')
                    ->orWhere('start_date', '<=', $date);
            })->where(function ($subQ) use ($date) {
                // End date is null or after/equal to current date
                $subQ->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
        });
    }

    /**
     * Check if customization is currently active
     */
    public function isActive($date = null): bool
    {
        $date = $date ?: now()->toDateString();

        $startValid = is_null($this->start_date) || $this->start_date <= $date;
        $endValid   = is_null($this->end_date) || $this->end_date >= $date;

        return $startValid && $endValid;
    }
}
