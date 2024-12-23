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

    public function getSubscriptions()
    {
        return $this->subscriptions()->get();
    }

    public function subscription(string $type = 'default'): ?PolarSubscription
    {
        try {
            return $this->subscriptions()
                ->where('type', $type)
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function subscriptionName(string $type = 'default'): ?string
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->items->first()?->product_id;
        }

        return null;
    }

    public function subscriptionPrice(string $type = 'default'): ?string
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->items->first()?->price_id;
        }

        return null;
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(PolarTransaction::class, 'billable')
            ->orderBy('created_at', 'desc');
    }

    public function getTransactions()
    {
        return $this->transactions()->get();
    }

    public function checkout(string $priceId, array $options = [])
    {
        $defaultOptions = [
            'success_url' => url(config('cashier-polar.success_url', '/dashboard')),
            'payment_processor' => 'stripe',
            'customer_name' => $this->name ?? null,
            'customer_email' => $this->email ?? null,
            'metadata' => [
                'billable_id' => $this->getKey(),
                'billable_type' => get_class($this),
            ],
        ];

        if ($this->polarId()) {
            $defaultOptions['customer_id'] = $this->polarId();
        }

        if (isset($options['metadata'])) {
            $defaultOptions['metadata'] = array_merge(
                $defaultOptions['metadata'],
                $options['metadata']
            );
            unset($options['metadata']);
        }

        return app(CashierPolar::class)->createCheckout(
            $priceId,
            array_merge($defaultOptions, $options)
        );
    }

    public function polarId(): ?string
    {
        return $this->customer?->polar_id;
    }

    public function subscribed(string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        return $subscription && $subscription->active();
    }

    public function subscribedToPlan(string $plan, string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        if (!$subscription || !$subscription->active()) {
            return false;
        }

        return $subscription->hasPlan($plan);
    }

    public function onPlan(string $plan): bool
    {
        return $this->subscriptions()
            ->whereHas('items', function ($query) use ($plan) {
                $query->where('price_id', $plan);
            })
            ->exists();
    }

    public function getOrCreateCustomer(array $attributes = []): PolarCustomer
    {
        if ($customer = $this->customer) {
            return $customer;
        }

        return $this->createCustomer($attributes);
    }

    public function createCustomer(array $attributes = []): PolarCustomer
    {
        if (isset($attributes['polar_id'])) {
            $existingCustomer = PolarCustomer::where('polar_id', $attributes['polar_id'])->first();
            if ($existingCustomer) {
                if ($existingCustomer->billable_id !== $this->getKey() || $existingCustomer->billable_type !== get_class($this)) {
                    $existingCustomer->update([
                        'billable_id' => $this->getKey(),
                        'billable_type' => get_class($this),
                    ]);
                }

                return $existingCustomer;
            }
        }

        if ($this->customer) {
            throw new \Exception('Customer already exists for this billable entity.');
        }

        return $this->customer()->create($attributes);
    }

    public function getSubscriptionAttribute(): ?PolarSubscription
    {
        try {
            return $this->subscription() ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function orders(array $filters = [])
    {
        return app(CashierPolar::class)->getOrders(array_merge([
            'customer_id' => $this->polarId(),
        ], $filters));
    }

    public function getInvoice(string $orderId)
    {
        $response = app(CashierPolar::class)->getOrderInvoice($orderId);

        return $response['url'] ?? null;
    }
}
