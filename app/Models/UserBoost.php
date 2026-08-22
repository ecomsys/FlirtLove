<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBoost extends Model
{
    protected $fillable = [
        'user_id', 'boost_plan_id', 'transaction_id', 'type', 'starts_at', 'ends_at', 'status'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(BoostPlan::class, 'boost_plan_id'); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }

    public function scopeActive($query) { return $query->where('status', 'active')->where('ends_at', '>', now()); }
    public function scopeOverdue($query) { return $query->where('status', 'active')->where('ends_at', '<=', now()); }

    public function isActive(): bool { return $this->status === 'active' && $this->ends_at->isFuture(); }
    
    public function expire(): bool { return $this->update(['status' => 'expired']); }
}

