<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cohort extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'cohort_order',
        'start_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cohort_order' => 'integer',
        'start_date' => 'date:Y-m-d',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // Default sorting by cohort_order ASC
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('cohort_order', 'asc');
        });
    }

    /**
     * Get IVA users assigned to this cohort
     */
    public function ivaUsers(): HasMany
    {
        return $this->hasMany(IvaUser::class, 'cohort_id');
    }

    /**
     * Scope active cohorts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the next available order number
     */
    public static function getNextOrder(): int
    {
        return static::withoutGlobalScope('ordered')->max('cohort_order') + 1;
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('cohort_order')->orderBy('name');
    }

    /**
     * Get formatted start date.
     */
    public function getFormattedStartDateAttribute()
    {
        return $this->start_date ? $this->start_date->format('Y-m-d') : null;
    }
}
