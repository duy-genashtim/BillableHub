<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvaManager extends Model
{
    use HasFactory;

    protected $table = 'iva_manager';

    protected $fillable = [
        'iva_id',
        'iva_manager_id',
        'manager_type_id',
        'region_id',
    ];

    protected $casts = [
        'iva_id'          => 'integer',
        'iva_manager_id'  => 'integer',
        'manager_type_id' => 'integer',
        'region_id'       => 'integer',
    ];

    /**
     * Get the user that is being managed.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(IvaUser::class, 'iva_id');
    }

    /**
     * Get the manager user.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(IvaUser::class, 'iva_manager_id');
    }

    /**
     * Get the region associated with this manager relationship.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Get the manager type from configuration settings.
     */
    public function managerType(): BelongsTo
    {
        return $this->belongsTo(ConfigurationSetting::class, 'manager_type_id');
    }

    /**
     * Scope a query to filter by manager type ID.
     */
    public function scopeByManagerTypeId($query, int $typeId)
    {
        return $query->where('manager_type_id', $typeId);
    }

    /**
     * Scope a query to filter by region ID.
     */
    public function scopeByRegion($query, int $regionId)
    {
        return $query->where('region_id', $regionId);
    }
}
