<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimedoctorV1User extends Model
{
    use HasFactory;

    protected $table = 'timedoctor_v1_user';

    protected $fillable = [
        'timedoctor_id',
        'tm_fullname',
        'tm_email',
        'is_active',
        'last_synced_at',
        'last_login',
        'iva_user_id',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime:Y-m-d\TH:i:s',
        'last_login'     => 'datetime:Y-m-d\TH:i:s',
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
}