<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvaUserLog extends Model
{
    use HasFactory;

    protected $table = 'iva_user_logs';

    protected $fillable = [
        'iva_user_id',
        'created_by',
        'log_type',
        'title',
        'content',
        'is_private',
    ];

    protected $casts = [
        'iva_user_id' => 'integer',
        'created_by'  => 'integer',
        'is_private'  => 'boolean',
    ];

    /**
     * Get the user that owns the log entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(IvaUser::class, 'iva_user_id');
    }

    /**
     * Get the user that created the log entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the log type configuration setting.
     */
    public function logTypeConfig(): BelongsTo
    {
        return $this->belongsTo(ConfigurationSetting::class, 'log_type', 'setting_value')
            ->whereHas('settingType', function ($query) {
                $query->where('key', 'iva_logs_type');
            });
    }

    /**
     * Scope to filter by log type
     */
    public function scopeByLogType($query, $logType)
    {
        return $query->where('log_type', $logType);
    }

    /**
     * Scope to filter public logs only
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope to filter private logs only
     */
    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    /**
     * Get formatted log type display name
     */
    public function getLogTypeDisplayAttribute()
    {
        $config = $this->logTypeConfig;
        return $config ? $config->description : ucfirst($this->log_type);
    }
}
