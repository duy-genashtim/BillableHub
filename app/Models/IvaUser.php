<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IvaUser extends Model
{
    use HasFactory;

    protected $table = 'iva_user';

    protected $fillable = [
        'full_name',
        'email',
        'hire_date',
        'end_date',
        'is_active',
        'region_id',
        'cohort_id',
        'work_status',
        'timedoctor_version',
    ];

    protected $casts = [
        'hire_date'          => 'date',
        'end_date'           => 'date',
        'is_active'          => 'boolean',
        'timedoctor_version' => 'integer',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class);
    }

    public function timedoctorUser(): HasOne
    {
        return $this->hasOne(TimedoctorV1User::class, 'iva_user_id');
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(WorklogsData::class, 'iva_id');
    }

    /**
     * Get the managers for this user.
     */
    public function managers(): HasMany
    {
        return $this->hasMany(IvaManager::class, 'iva_id');
    }

    /**
     * Get the subordinates for this user (when user is a manager).
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(IvaManager::class, 'iva_manager_id');
    }

    /**
     * Get the user customizations.
     */
    public function customizations(): HasMany
    {
        return $this->hasMany(IvaUserCustomize::class, 'iva_user_id');
    }

    /**
     * Get the user changelogs.
     */
    public function changelogs(): HasMany
    {
        return $this->hasMany(IvaUserChangelog::class, 'iva_user_id');
    }

    /**
     * Get the user logs.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(IvaUserLog::class, 'iva_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRegion($query, $regionId)
    {
        return $query->where('region_id', $regionId);
    }

    /**
     * Load manager information for the user
     */
    public function loadManagerInfo()
    {
        $managers = $this->managers()
            ->with(['manager', 'managerType', 'region'])
            ->get()
            ->map(function ($manager) {
                return [
                    'id'           => $manager->id,
                    'manager_name' => $manager->manager ? $manager->manager->full_name : null,
                    'manager_type' => $manager->managerType ? $manager->managerType->setting_value : null,
                    'region_name'  => $manager->region ? $manager->region->name : null,
                ];
            });

        $this->setAttribute('managers_info', $managers);

        return $this;
    }

    /**
     * Get formatted hire date.
     */
    public function getFormattedHireDateAttribute()
    {
        return $this->hire_date ? $this->hire_date->format('Y-m-d') : null;
    }

    /**
     * Get formatted end date.
     */
    public function getFormattedEndDateAttribute()
    {
        return $this->end_date ? $this->end_date->format('Y-m-d') : null;
    }

    public function timedoctorV2User(): HasOne
    {
        return $this->hasOne(TimedoctorV2User::class, 'iva_user_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Set default timedoctor_version if not provided
            if (! isset($model->timedoctor_version)) {
                $model->timedoctor_version = 1;
            }
        });
    }
}