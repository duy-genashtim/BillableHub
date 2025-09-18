<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigurationSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'setting_type_id',
        'setting_value',
        'description',
        'is_active',
        'added_by',
        'order',
        'is_system',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the setting type that this setting belongs to.
     */
    public function settingType()
    {
        return $this->belongsTo(ConfigurationSettingType::class, 'setting_type_id');
    }

    /**
     * Scope a query to only include settings of a specific type
     */
    public function scopeOfType($query, $typeId)
    {
        return $query->where('setting_type_id', $typeId);
    }

    /**
     * Scope a query to only include active settings
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        // Default ordering by type and then order
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('setting_type_id')->orderBy('order');
        });
    }

    public function userCustomizations(): HasMany
    {
        return $this->hasMany(IvaUserCustomize::class, 'setting_id');
    }
}
