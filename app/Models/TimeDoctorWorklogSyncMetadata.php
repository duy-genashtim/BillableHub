<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeDoctorWorklogSyncMetadata extends Model
{
    use HasFactory;

    protected $table = 'timedoctor_worklog_sync_metadata';

    protected $fillable = [
        'sync_date',
        'status',
        'started_at',
        'completed_at',
        'is_synced',
        'total_records',
        'synced_records',
        'error_message',
    ];

    protected $casts = [
        'sync_date' => 'date:Y-m-d',
        'started_at' => 'datetime:Y-m-d\TH:i:s',
        'completed_at' => 'datetime:Y-m-d\TH:i:s',
        'is_synced' => 'boolean',
        'total_records' => 'integer',
        'synced_records' => 'integer',
    ];
}
