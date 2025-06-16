<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'batch_order',
        'start_date',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'batch_order' => 'integer',
        'start_date'  => 'date',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // Default sorting by batch_order ASC
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('batch_order', 'asc');
        });
    }

    /**
     * Get IVA users assigned to this batch
     */
    public function ivaUsers(): HasMany
    {
        return $this->hasMany(IvaUser::class, 'batch_id');
    }

    /**
     * Scope active batches
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
        return static::withoutGlobalScope('ordered')->max('batch_order') + 1;
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('batch_order')->orderBy('name');
    }

    /**
     * Get formatted start date.
     */
    public function getFormattedStartDateAttribute()
    {
        return $this->start_date ? $this->start_date->format('Y-m-d') : null;
    }
}