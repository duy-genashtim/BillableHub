<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimedoctorV2User extends Model
{
    use HasFactory;

    protected $table = 'timedoctor_v2_user';

    protected $fillable = [
        'timedoctor_id',
        'tm_fullname',
        'tm_email',
        'timezone',
        'profile_timezone',
        'role',
        'only_project_ids',
        'manager_ids',
        'tag_ids',
        'silent_info',
        'is_active',
        'last_synced_at',
        'last_login',
        'iva_user_id',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'last_synced_at'   => 'datetime',
        'last_login'       => 'datetime',
        'only_project_ids' => 'array',
        'manager_ids'      => 'array',
        'tag_ids'          => 'array',
        'silent_info'      => 'array',
    ];

    public function ivaUser(): BelongsTo
    {
        return $this->belongsTo(IvaUser::class, 'iva_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNeedsSyncing($query, int $hoursThreshold = 24)
    {
        return $query->where(function ($query) use ($hoursThreshold) {
            $query->whereNull('last_synced_at')
                ->orWhere('last_synced_at', '<', now()->subHours($hoursThreshold));
        });
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
