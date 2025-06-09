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

    public function timedoctorUser(): HasOne
    {
        return $this->hasOne(TimedoctorV1User::class, 'iva_user_id');
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(WorklogsData::class, 'iva_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRegion($query, $regionId)
    {
        return $query->where('region_id', $regionId);
    }
}