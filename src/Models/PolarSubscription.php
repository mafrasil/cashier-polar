<?php

namespace Mafrasil\CashierPolar\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Mafrasil\CashierPolar\CashierPolar;
use Mafrasil\CashierPolar\Enums\SubscriptionStatus;

class PolarSubscription extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = [
        'name',
        'price',
        'price_id',
        'product_id',
        'active',
        'cancelled',
        'on_grace_period',
        'interval',
        'description',
        'days_until_ends',
        'days_until_trial_ends',
    ];

    protected $with = ['items'];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'metadata' => 'array',
        'status' => SubscriptionStatus::class,
    ];

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PolarSubscriptionItem::class, 'subscription_id');
    }

    public function active(): bool
    {
        return ($this->status !== SubscriptionStatus::CANCELED) ||
            ($this->status === SubscriptionStatus::CANCELED && ($this->onTrial() || $this->onGracePeriod()));
    }

    public function cancelled(): bool
    {
        return $this->status === SubscriptionStatus::CANCELED;
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

    public function onGracePeriod(): bool
    {
        return $this->cancel_at_period_end &&
        $this->current_period_end &&
        $this->current_period_end->isFuture();
    }

    public function recurring(): bool
    {
        return $this->items->first()?->is_recurring ?? false;
    }

    public function ended(): bool
    {
        return $this->cancelled() &&
        $this->current_period_end &&
        $this->current_period_end->isPast();
    }

    public function revoke(): array
    {
        return app(CashierPolar::class)->revokeSubscription($this->polar_id);
    }

    public function resume(): array
    {
        return app(CashierPolar::class)->resumeSubscription($this->polar_id);
    }

    public function cancel(): array
    {
        if ($this->cancelled() || $this->cancel_at_period_end) {
            throw new \Exception('Subscription is already cancelled or scheduled for cancellation.');
        }

        return app(CashierPolar::class)->cancelSubscription($this->polar_id);
    }

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

    public function getIntervalAttribute(): ?string
    {
        if (!$this->items) {
            return null;
        }

        return $this->items->first()?->recurring_interval;
    }

    public function getDescriptionAttribute(): ?string
    {
        if (!$this->items) {
            return null;
        }

        return $this->items->first()?->product_description;
    }

    public function endsAt(): ?Carbon
    {
        return $this->ends_at;
    }

    public function expired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function trialEndsAt(): ?Carbon
    {
        return $this->trial_ends_at;
    }

    public function endDate(): ?string
    {
        return $this->ends_at?->format('Y-m-d');
    }

    public function trialEndDate(): ?string
    {
        return $this->trial_ends_at?->format('Y-m-d');
    }

    public function getProductIdAttribute(): ?string
    {
        if (!$this->items) {
            return null;
        }

        return $this->items->first()?->product_id;
    }

    public function getPriceIdAttribute(): ?string
    {
        if (!$this->items) {
            return null;
        }

        return $this->items->first()?->price_id;
    }

    public function currentPeriodStart(): ?Carbon
    {
        return $this->current_period_start;
    }

    public function currentPeriodEnd(): ?Carbon
    {
        return $this->current_period_end;
    }

    public function daysUntilPeriodEnds(): ?int
    {
        return $this->current_period_end ? now()->diffInDays($this->current_period_end) : null;
    }

    public function currentPeriod(): ?string
    {
        if (!$this->current_period_start || !$this->current_period_end) {
            return null;
        }

        return $this->current_period_start->format('Y-m-d') . ' to ' . $this->current_period_end->format('Y-m-d');
    }

    public function withinPeriod(): bool
    {
        return $this->current_period_start &&
        $this->current_period_end &&
        now()->between($this->current_period_start, $this->current_period_end);
    }

    public function change(string $priceId): array
    {
        return app(CashierPolar::class)->updateSubscription($this->polar_id, $priceId);
    }

    public function getActiveAttribute(): bool
    {
        return $this->active();
    }

    public function getCancelledAttribute(): bool
    {
        return $this->cancelled();
    }

    public function getDaysUntilEndsAttribute(): ?int
    {
        return $this->ends_at ? now()->diffInDays($this->ends_at) : null;
    }

    public function getDaysUntilTrialEndsAttribute(): ?int
    {
        return $this->trial_ends_at ? now()->diffInDays($this->trial_ends_at) : null;
    }

    public function getOnGracePeriodAttribute(): bool
    {
        return $this->onGracePeriod();
    }
}
