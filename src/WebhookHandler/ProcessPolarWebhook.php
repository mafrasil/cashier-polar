<?php

namespace Mafrasil\CashierPolar\WebhookHandler;

use Mafrasil\CashierPolar\Models\PolarCustomer;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessPolarWebhook extends ProcessWebhookJob
{
    public function handle()
    {
        $decoded = json_decode($this->webhookCall, true);
        $payload = $decoded['payload'];
        $type = $payload['type'];

        match ($type) {
            'checkout.created' => $this->handleCheckoutCreated($payload),
            'checkout.updated' => $this->handleCheckoutUpdated($payload),
            'subscription.created' => $this->handleSubscriptionCreated($payload),
            'subscription.updated' => $this->handleSubscriptionUpdated($payload),
            'subscription.active' => $this->handleSubscriptionActive($payload),
            'subscription.revoked' => $this->handleSubscriptionRevoked($payload),
            'subscription.canceled' => $this->handleSubscriptionCanceled($payload),
            default => $this->handleUnknownWebhook($payload),
        };
    }

    protected function handleCheckoutCreated(array $payload): void
    {
        $billable = $this->getBillableFromCustomerId($payload['customer_id']);

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.$payload['customer_id']);

            return;
        }

        $transaction = $billable->transactions()->create([
            'polar_id' => $payload['id'],
            'checkout_id' => $payload['checkout_id'],
            'status' => $payload['status'],
            'total' => $payload['total'],
            'tax' => $payload['tax'] ?? 0,
            'currency' => $payload['currency'],
            'billed_at' => now(),
        ]);

        event(new \Mafrasil\CashierPolar\Events\CheckoutCreated($transaction, $payload));
    }

    protected function handleCheckoutUpdated(array $payload): void
    {
        $billable = $this->getBillableFromCustomerId($payload['customer_id']);

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.$payload['customer_id']);

            return;
        }

        if ($transaction = $billable->transactions()->where('checkout_id', $payload['checkout_id'])->first()) {
            $transaction->update([
                'status' => $payload['status'],
            ]);

            event(new \Mafrasil\CashierPolar\Events\CheckoutUpdated($transaction, $payload));
        }
    }

    protected function handleSubscriptionCreated(array $payload): void
    {
        logger()->info('Processing subscription created', $payload);
        $billable = $this->getBillableFromCustomerId($payload['customer_id']);

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.$payload['customer_id']);

            return;
        }

        $subscription = $billable->subscriptions()->create([
            'polar_id' => $payload['id'],
            'type' => 'default',
            'status' => $payload['status'],
            'trial_ends_at' => $payload['trial_ends_at'] ?? null,
        ]);

        event(new \Mafrasil\CashierPolar\Events\SubscriptionCreated($subscription, $payload));
    }

    protected function handleSubscriptionActive(array $payload): void
    {
        $billable = $this->getBillableFromCustomerId($payload['customer_id']);

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.$payload['customer_id']);

            return;
        }

        if ($subscription = $billable->subscriptions()->where('polar_id', $payload['id'])->first()) {
            $subscription->update(['status' => 'active']);
            event(new \Mafrasil\CashierPolar\Events\SubscriptionActive($subscription, $payload));
        }
    }

    protected function handleSubscriptionCanceled(array $payload): void
    {
        $billable = $this->getBillableFromCustomerId($payload['customer_id']);

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.$payload['customer_id']);

            return;
        }

        if ($subscription = $billable->subscriptions()->where('polar_id', $payload['id'])->first()) {
            $subscription->update([
                'status' => 'canceled',
                'ends_at' => now(),
            ]);
            event(new \Mafrasil\CashierPolar\Events\SubscriptionCanceled($subscription, $payload));
        }
    }

    protected function handleSubscriptionRevoked(array $payload): void
    {
        $billable = $this->getBillableFromCustomerId($payload['customer_id']);

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.$payload['customer_id']);

            return;
        }

        if ($subscription = $billable->subscriptions()->where('polar_id', $payload['id'])->first()) {
            $subscription->update([
                'status' => 'revoked',
                'ends_at' => now(),
            ]);
            event(new \Mafrasil\CashierPolar\Events\SubscriptionRevoked($subscription, $payload));
        }
    }

    protected function handleSubscriptionUpdated(array $payload): void
    {
        $billable = $this->getBillableFromCustomerId($payload['customer_id']);

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.$payload['customer_id']);

            return;
        }

        if ($subscription = $billable->subscriptions()->where('polar_id', $payload['id'])->first()) {
            $subscription->update([
                'status' => $payload['status'],
            ]);
            event(new \Mafrasil\CashierPolar\Events\SubscriptionUpdated($subscription, $payload));
        }
    }

    protected function handleUnknownWebhook(array $payload): void
    {
        logger()->info('Unknown webhook type received from Polar', $payload);
    }

    protected function getBillableFromCustomerId(string $customerId)
    {
        return PolarCustomer::where('polar_id', $customerId)
            ->first()
            ?->billable;
    }
}
