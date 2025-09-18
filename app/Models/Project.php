<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'timedoctor_version',
        'timedoctor_id',
        'project_name',
        'is_active',
        'description',
        'last_synced_at',
    ];

    protected $casts = [
        'timedoctor_version' => 'integer',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function worklogs(): HasMany
    {
        return $this->hasMany(WorklogsData::class, 'project_id');
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

    public function scopeByVersion($query, int $version)
    {
        return $query->where('timedoctor_version', $version);
    }
}
