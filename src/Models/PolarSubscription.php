<?php

namespace Mafrasil\CashierPolar\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Mafrasil\CashierPolar\CashierPolar;

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
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    public function active(): bool
    {
        return $this->status === 'active';
    }

    public function cancelled(): bool
    {
        return $this->status === 'canceled';
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function hasPlan(string $plan): bool
    {
        return $this->items()->where('price_id', $plan)->exists();
    }

    public function cancel(): self
    {
        app(CashierPolar::class)->cancelSubscription($this->polar_id);

        return $this;
    }

    public function resume(): self
    {
        if (!$this->cancelled()) {
            throw new \LogicException('Unable to resume subscription that is not cancelled.');
        }

        $this->status = 'active';
        $this->ends_at = null;
        $this->save();

        return $this;
    }

    /**
     * Get the subscription name (product ID).
     *
     * @return string|null
     */
    public function getNameAttribute(): ?string
    {
        if (!$this->items) {
            return null;
        }

        $item = $this->items->first();
        if (!$item) {
            return null;
        }

        return $item->product_name ?? 'Product ' . $item->product_id;
    }

    /**
     * Get the subscription price.
     *
     * @return string|null
     */
    public function getPriceAttribute(): ?string
    {
        if (!$this->items) {
            return null;
        }

        $item = $this->items->first();
        if (!$item || !$item->price_amount || !$item->price_currency) {
            return null;
        }

        return number_format($item->price_amount / 100, 2) . ' ' . strtoupper($item->price_currency);
    }

    /**
     * Get the billing interval.
     *
     * @return string|null
     */
    public function getIntervalAttribute(): ?string
    {
        if (!$this->items) {
            return null;
        }

        return $this->items->first()?->recurring_interval;
    }

    /**
     * Get the subscription description.
     *
     * @return string|null
     */
    public function getDescriptionAttribute(): ?string
    {
        if (!$this->items) {
            return null;
        }

        return $this->items->first()?->product_description;
    }

    /**
     * Get the date when the subscription ends.
     */
    public function endsAt(): ?Carbon
    {
        return $this->ends_at;
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     */
    public function onGracePeriod(): bool
    {
        return $this->cancelled() && $this->ends_at?->isFuture();
    }

    /**
     * Determine if the subscription is expired.
     */
    public function expired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Get the trial end date.
     */
    public function trialEndsAt(): ?Carbon
    {
        return $this->trial_ends_at;
    }

    /**
     * Get the subscription end date.
     */
    public function endDate(): ?string
    {
        return $this->ends_at?->format('Y-m-d');
    }

    /**
     * Get the trial end date.
     */
    public function trialEndDate(): ?string
    {
        return $this->trial_ends_at?->format('Y-m-d');
    }

    /**
     * Get days until subscription ends.
     */
    public function daysUntilEnds(): ?int
    {
        return $this->ends_at ? now()->diffInDays($this->ends_at) : null;
    }

    /**
     * Get days until trial ends.
     */
    public function daysUntilTrialEnds(): ?int
    {
        return $this->trial_ends_at ? now()->diffInDays($this->trial_ends_at) : null;
    }

    /**
     * Get the subscription product ID.
     *
     * @return string|null
     */
    public function getProductIdAttribute(): ?string
    {
        if (!$this->items) {
            return null;
        }

        return $this->items->first()?->product_id;
    }

    /**
     * Get the subscription price ID.
     *
     * @return string|null
     */
    public function getPriceIdAttribute(): ?string
    {
        if (!$this->items) {
            return null;
        }

        return $this->items->first()?->price_id;
    }
}
