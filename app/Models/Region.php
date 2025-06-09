<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'region_order',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'region_order' => 'integer',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // Default sorting by region_order ASC
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('region_order', 'asc');
        });
    }

    /**
     * Get IVA users assigned to this region
     */
    public function ivaUsers(): HasMany
    {
        return $this->hasMany(IvaUser::class, 'region_id');
    }

    /**
     * Scope active regions
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
        return static::withoutGlobalScope('ordered')->max('region_order') + 1;
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('region_order')->orderBy('name');
    }
}
