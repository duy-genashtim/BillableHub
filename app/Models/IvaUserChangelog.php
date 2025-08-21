<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvaUserChangelog extends Model
{
    use HasFactory;

    protected $table = 'iva_user_changelogs';

    protected $fillable = [
        'iva_user_id',
        'field_changed',
        'old_value',
        'new_value',
        'change_reason',
        'changed_by_name',
        'changed_by_email',
        'effective_date',
    ];

    protected $casts = [
        'iva_user_id'    => 'integer',
        'effective_date' => 'date:Y-m-d',
    ];

    /**
     * Get the user that owns the changelog entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(IvaUser::class, 'iva_user_id');
    }
}
