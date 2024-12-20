<?php

namespace Mafrasil\CashierPolar\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Mafrasil\CashierPolar\CashierPolar;
use Mafrasil\CashierPolar\Models\PolarCustomer;
use Mafrasil\CashierPolar\Models\PolarSubscription;
use Mafrasil\CashierPolar\Models\PolarTransaction;

trait Billable
{
    public function customer(): MorphOne
    {
        return $this->morphOne(PolarCustomer::class, 'billable');
    }

    public function getCustomerAttribute(): ?PolarCustomer
    {
        return $this->customer()->first();
    }

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(PolarSubscription::class, 'billable')
            ->orderBy('created_at', 'desc');
    }

    public function subscription(string $type = 'default'): ?PolarSubscription
    {
        return $this->subscriptions()->where('type', $type)->first();
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(PolarTransaction::class, 'billable')
            ->orderBy('created_at', 'desc');
    }

    public function checkout(string $priceId, array $options = [])
    {
        return app(CashierPolar::class)->createCheckout(
            $priceId,
            array_merge([
                'success_url' => url(config('cashier-polar.success_url', '/dashboard')),
                'customer_id' => $this->polarId(),
            ], $options)
        );
    }

    public function polarId(): ?string
    {
        return $this->customer?->polar_id;
    }

    public function onTrial(string $type = 'default'): bool
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->onTrial();
        }

        return false;
    }

    public function subscribed(string $type = 'default'): bool
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->valid();
        }

        return false;
    }

    public function hasExpiredTrial(string $type = 'default'): bool
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->hasExpiredTrial();
        }

        return false;
    }

    public function onPlan(string $plan): bool
    {
        return $this->subscriptions()
            ->whereHas('items', function ($query) use ($plan) {
                $query->where('price_id', $plan);
            })->exists();
    }

    public function createOrGetCustomer(array $attributes = []): PolarCustomer
    {
        if ($customer = $this->customer) {
            return $customer;
        }

        return $this->createCustomer($attributes);
    }

    public function createCustomer(array $attributes = []): PolarCustomer
    {
        if ($this->customer) {
            throw new \Exception('Customer already exists for this billable entity.');
        }

        return $this->customer()->create($attributes);
    }
}
