<?php

namespace Mafrasil\CashierPolar\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PolarSubscription extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PolarSubscriptionItem::class, 'subscription_id');
    }

    public function valid(): bool
    {
        return $this->active() && !$this->ended();
    }

    public function active(): bool
    {
        return $this->status === 'active' || $this->onGracePeriod();
    }

    public function canceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function ended(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function onGracePeriod(): bool
    {
        return $this->canceled() && $this->ends_at && $this->ends_at->isFuture();
    }
}
