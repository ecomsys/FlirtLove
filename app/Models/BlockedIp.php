<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedIp extends Model
{
    protected $fillable = [
        'ip_address',
        'reason',
        'blocked_by',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }
}