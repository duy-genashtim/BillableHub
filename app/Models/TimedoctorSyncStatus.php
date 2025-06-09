<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimedoctorSyncStatus extends Model
{
    use HasFactory;

    protected $table = 'timedoctor_sync_status';

    protected $fillable = [
        'entity_type',
        'last_synced_at',
        'last_sync_started_at',
        'status',
        'sync_details',
        'error_message',
    ];

    protected $casts = [
        'last_synced_at'       => 'datetime',
        'last_sync_started_at' => 'datetime',
        'sync_details'         => 'array',
    ];

    const ENTITY_USERS    = 'users';
    const ENTITY_PROJECTS = 'projects';
    const ENTITY_TASKS    = 'tasks';
    const ENTITY_WORKLOGS = 'worklogs';

    const STATUS_IDLE        = 'idle';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_FAILED      = 'failed';

    public function scopeOfType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function markAsStarted(array $details = null): self
    {
        $this->update([
            'status'               => self::STATUS_IN_PROGRESS,
            'last_sync_started_at' => now(),
            'sync_details'         => $details,
            'error_message'        => null,
        ]);

        return $this;
    }

    public function markAsCompleted(array $details = null): self
    {
        $this->update([
            'status'         => self::STATUS_COMPLETED,
            'last_synced_at' => now(),
            'sync_details'   => $details ?: $this->sync_details,
            'error_message'  => null,
        ]);

        return $this;
    }

    public function markAsFailed(string $errorMessage, array $details = null): self
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'sync_details'  => $details ?: $this->sync_details,
            'error_message' => $errorMessage,
        ]);

        return $this;
    }

    public function markAsIdle(): self
    {
        $this->update([
            'status'        => self::STATUS_IDLE,
            'error_message' => null,
        ]);

        return $this;
    }
}
