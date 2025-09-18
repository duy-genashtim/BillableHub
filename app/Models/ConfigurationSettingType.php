<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigurationSettingType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'configuration_settings_type';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'name',
        'description',
        'setting_category',
        'for_user_customize',
        'allow_edit',
        'allow_delete',
        'allow_create',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'for_user_customize' => 'boolean',
        'allow_edit' => 'boolean',
        'allow_delete' => 'boolean',
        'allow_create' => 'boolean',
    ];

    /**
     * Get the settings associated with this type.
     */
    public function settings()
    {
        return $this->hasMany(ConfigurationSetting::class, 'setting_type_id');
    }

    /**
     * Scope a query to only include setting types of a specific category
     */
    public function scopeOfCategory($query, $category)
    {
        return $query->where('setting_category', $category);
    }

    public function scopeUserCustomizable($query)
    {
        return $query->where('for_user_customize', true);
    }
}
