<?php

namespace Mafrasil\CashierPolar\WebhookHandler;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mafrasil\CashierPolar\Enums\SubscriptionStatus;
use Mafrasil\CashierPolar\Models\PolarCustomer;

class ProcessPolarWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected array $payload)
    {}

    public function handle()
    {
        Log::info('Processing Polar Webhook', [
            'type' => $this->payload['type'],
            'payload' => $this->payload,
        ]);

        try {
            $type = $this->payload['type'];
            $result = match ($type) {
                'checkout.created' => $this->handleCheckoutCreated($this->payload),
                'checkout.updated' => $this->handleCheckoutUpdated($this->payload),
                'order.created' => $this->handleOrderCreated($this->payload),
                'subscription.created' => $this->handleSubscriptionCreated($this->payload),
                'subscription.updated' => $this->handleSubscriptionUpdated($this->payload),
                'subscription.active' => $this->handleSubscriptionActive($this->payload),
                'subscription.revoked' => $this->handleSubscriptionRevoked($this->payload),
                'subscription.canceled' => $this->handleSubscriptionCanceled($this->payload),
                default => $this->handleUnknownWebhook($this->payload),
            };

            if ($result !== false) {
                Log::info('Polar Webhook Processed Successfully', [
                    'type' => $type,
                ]);
            } else {
                Log::warning('Polar Webhook Processing Incomplete', [
                    'type' => $type,
                    'reason' => 'Required data not found',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Polar Webhook Processing Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $this->payload,
            ]);
            throw $e;
        }
    }

    protected function handleCheckoutCreated(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;

        $billable = $this->getBillableFromCustomerId($data['customer_id'] ?? null);

        if (!$billable && isset($data['metadata']['billable_id'], $data['metadata']['billable_type'])) {
            $billableType = $data['metadata']['billable_type'];
            $billable = $billableType::find($data['metadata']['billable_id']);

            if ($billable && isset($data['customer_id'])) {
                $billable->getOrCreateCustomer([
                    'polar_id' => $data['customer_id'],
                    'name' => $data['customer_name'] ?? $billable->name ?? 'Unknown',
                    'email' => $data['customer_email'] ?? $billable->email ?? 'unknown@example.com',
                ]);
            }
        }

        if (!$billable) {
            logger()->error('No billable found for customer_id: ' . ($data['customer_id'] ?? 'null') . ' or billable_id: ' . ($data['metadata']['billable_id'] ?? 'not provided'));

            return false;
        }

        $transaction = $billable->transactions()->create([
            'polar_id' => $data['id'],
            'checkout_id' => $data['id'],
            'status' => $data['status'],
            'total' => $data['total_amount'],
            'tax' => $data['tax_amount'] ?? 0,
            'currency' => $data['currency'],
            'billed_at' => now(),
        ]);

        event(new \Mafrasil\CashierPolar\Events\CheckoutCreated($transaction, $payload));

        return true;
    }

    protected function handleCheckoutUpdated(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;

        $billable = $this->getBillableFromCustomerId($data['customer_id'] ?? null);

        if (!$billable) {
            logger()->error('No billable found for customer_id: ' . ($data['customer_id'] ?? 'null'));

            return false;
        }

        $transaction = $billable->transactions()->where('checkout_id', $data['id'] ?? null)->first();
        if (!$transaction) {
            logger()->error('No transaction found for checkout_id: ' . ($data['id'] ?? 'null'));

            return false;
        }

        $transaction->update([
            'status' => $data['status'] ?? 'unknown',
        ]);

        event(new \Mafrasil\CashierPolar\Events\CheckoutUpdated($transaction, $payload));

        return true;
    }

    protected function handleOrderCreated(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;

        $billable = $this->getBillableFromCustomerId($data['customer_id'] ?? null);

        if (!$billable && isset($data['metadata']['billable_id'], $data['metadata']['billable_type'])) {
            $billableType = $data['metadata']['billable_type'];
            $billable = $billableType::find($data['metadata']['billable_id']);

            if ($billable && isset($data['customer_id'])) {
                $billable->getOrCreateCustomer([
                    'polar_id' => $data['customer_id'],
                    'name' => $data['customer']['name'] ?? $billable->name ?? 'Unknown',
                    'email' => $data['customer']['email'] ?? $billable->email ?? 'unknown@example.com',
                ]);
            }
        }

        if (!$billable) {
            logger()->error('No billable found for customer_id: ' . ($data['customer_id'] ?? 'null'));

            return false;
        }

        $transaction = $billable->transactions()->create([
            'polar_id' => $data['id'],
            'polar_subscription_id' => $data['subscription_id'] ?? null,
            'checkout_id' => $data['checkout_id'] ?? null,
            'status' => 'completed',
            'total' => $data['amount'] ?? 0,
            'tax' => $data['tax_amount'] ?? 0,
            'currency' => $data['currency'],
            'billed_at' => now()->parse($data['created_at']),
            'metadata' => [
                'billing_reason' => $data['billing_reason'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
            ],
        ]);

        event(new \Mafrasil\CashierPolar\Events\OrderCreated($transaction, $payload));

        return true;
    }

    protected function handleSubscriptionCreated(array $payload): bool
    {
        return DB::transaction(function () use ($payload) {
            $data = $payload['data'] ?? $payload;
            logger()->info('Processing subscription created', $data);

            $billable = $this->getBillableFromCustomerId($data['customer_id'] ?? null);

            if (!$billable && isset($data['metadata']['billable_id'], $data['metadata']['billable_type'])) {
                $billableType = $data['metadata']['billable_type'];
                $billable = $billableType::find($data['metadata']['billable_id']);

                if ($billable && isset($data['customer_id'])) {
                    $billable->getOrCreateCustomer([
                        'polar_id' => $data['customer_id'],
                        'name' => $data['customer']['name'] ?? $billable->name ?? 'Unknown',
                        'email' => $data['customer']['email'] ?? $billable->email ?? 'unknown@example.com',
                    ]);
                }
            }

            if (!$billable) {
                logger()->error('No billable found for customer_id: ' . ($data['customer_id'] ?? 'null'));

                return false;
            }

            $subscription = $billable->subscriptions()->where('polar_id', $data['id'])->first();

            $status = match ($data['status'] ?? 'incomplete') {
                'active' => SubscriptionStatus::ACTIVE->value,
                'canceled' => SubscriptionStatus::CANCELED->value,
                'revoked' => SubscriptionStatus::REVOKED->value,
                default => SubscriptionStatus::INCOMPLETE->value,
            };

            if ($subscription) {
                $subscription->update([
                    'type' => 'default',
                    'status' => $status,
                    'trial_ends_at' => isset($data['trial_ends_at']) ? now()->parse($data['trial_ends_at']) : null,
                    'ends_at' => isset($data['current_period_end']) ? now()->parse($data['current_period_end']) : null,
                    'current_period_start' => isset($data['current_period_start']) ? now()->parse($data['current_period_start']) : null,
                    'current_period_end' => isset($data['current_period_end']) ? now()->parse($data['current_period_end']) : null,
                    'started_at' => isset($data['started_at']) ? now()->parse($data['started_at']) : null,
                    'cancel_at_period_end' => $data['cancel_at_period_end'] ?? false,
                ]);
            } else {
                $subscription = $billable->subscriptions()->create([
                    'polar_id' => $data['id'],
                    'type' => 'default',
                    'status' => $status,
                    'trial_ends_at' => isset($data['trial_ends_at']) ? now()->parse($data['trial_ends_at']) : null,
                    'ends_at' => isset($data['current_period_end']) ? now()->parse($data['current_period_end']) : null,
                    'current_period_start' => isset($data['current_period_start']) ? now()->parse($data['current_period_start']) : null,
                    'current_period_end' => isset($data['current_period_end']) ? now()->parse($data['current_period_end']) : null,
                    'started_at' => isset($data['started_at']) ? now()->parse($data['started_at']) : null,
                    'cancel_at_period_end' => $data['cancel_at_period_end'] ?? false,
                ]);
            }

            $item = $subscription->items()->where('price_id', $data['price_id'])->first();

            if ($item) {
                $item->update([
                    'product_name' => $data['product']['name'] ?? null,
                    'product_description' => $data['product']['description'] ?? null,
                    'price_currency' => $data['price']['currency'] ?? $data['currency'],
                    'price_amount' => $data['price']['amount'] ?? $data['amount'],
                    'recurring_interval' => $data['recurring_interval'],
                    'is_recurring' => $data['product']['is_recurring'] ?? false,
                    'status' => $status,
                ]);
            } else {
                $subscription->items()->create([
                    'product_id' => $data['product_id'],
                    'product_name' => $data['product']['name'] ?? null,
                    'product_description' => $data['product']['description'] ?? null,
                    'price_id' => $data['price_id'],
                    'price_currency' => $data['price']['currency'] ?? $data['currency'],
                    'price_amount' => $data['price']['amount'] ?? $data['amount'],
                    'recurring_interval' => $data['recurring_interval'],
                    'is_recurring' => $data['product']['is_recurring'] ?? false,
                    'status' => $status,
                    'quantity' => 1,
                ]);
            }

            event(new \Mafrasil\CashierPolar\Events\SubscriptionCreated($subscription, $payload));

            return true;
        });
    }

    protected function handleSubscriptionActive(array $payload): bool
    {
        return DB::transaction(function () use ($payload) {
            $data = $payload['data'] ?? $payload;

            logger()->debug('Subscription Active Payload', [
                'data' => $data,
                'product' => $data['product'] ?? null,
                'price' => $data['price'] ?? null,
            ]);

            $billable = $this->getBillableFromCustomerId($data['customer_id']);

            if (!$billable) {
                logger()->error('No billable found for customer_id: ' . $data['customer_id']);

                return false;
            }

            $subscription = $billable->subscriptions()->where('polar_id', $data['id'])->first();
            if (!$subscription) {
                logger()->error('No subscription found for polar_id: ' . $data['id']);

                return false;
            }

            $subscription->update([
                'status' => 'active',
                'trial_ends_at' => isset($data['trial_ends_at']) ? now()->parse($data['trial_ends_at']) : null,
                'ends_at' => isset($data['current_period_end']) ? now()->parse($data['current_period_end']) : null,
            ]);

            if ($item = $subscription->items()->where('price_id', $data['price_id'])->first()) {
                $item->update([
                    'status' => 'active',
                    'product_name' => $data['product']['name'] ?? null,
                    'product_description' => $data['product']['description'] ?? null,
                    'price_currency' => $data['price']['currency'] ?? $data['currency'] ?? null,
                    'price_amount' => $data['price']['amount'] ?? $data['amount'] ?? null,
                ]);
            } else {
                $subscription->items()->create([
                    'product_id' => $data['product_id'],
                    'price_id' => $data['price_id'],
                    'status' => 'active',
                    'quantity' => 1,
                    'product_name' => $data['product']['name'] ?? null,
                    'product_description' => $data['product']['description'] ?? null,
                    'price_currency' => $data['price']['currency'] ?? $data['currency'] ?? null,
                    'price_amount' => $data['price']['amount'] ?? $data['amount'] ?? null,
                    'recurring_interval' => $data['recurring_interval'] ?? null,
                    'is_recurring' => $data['product']['is_recurring'] ?? false,
                ]);
            }

            $billable->transactions()->create([
                'polar_id' => $data['id'] . '_' . now()->timestamp,
                'polar_subscription_id' => $data['id'],
                'status' => 'completed',
                'total' => $data['price']['amount'] ?? $data['amount'] ?? 0,
                'tax' => $data['tax_amount'] ?? 0,
                'currency' => $data['price']['currency'] ?? $data['currency'],
                'billed_at' => now(),
            ]);

            event(new \Mafrasil\CashierPolar\Events\SubscriptionActive($subscription, $payload));

            return true;
        });
    }

    protected function handleSubscriptionCanceled(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;

        $billable = $this->getBillableFromCustomerId($data['customer_id'] ?? null);

        if (!$billable) {
            logger()->error('No billable found for customer_id: ' . ($data['customer_id'] ?? 'null'));

            return false;
        }

        $subscription = $billable->subscriptions()->where('polar_id', $data['id'] ?? null)->first();
        if (!$subscription) {
            logger()->error('No subscription found for polar_id: ' . ($data['id'] ?? 'null'));

            return false;
        }

        $subscription->update([
            'status' => 'canceled',
            'ends_at' => isset($data['current_period_end'])
            ? now()->parse($data['current_period_end'])
            : now(),
        ]);

        event(new \Mafrasil\CashierPolar\Events\SubscriptionCanceled($subscription, $payload));

        return true;
    }

    protected function handleSubscriptionRevoked(array $payload): bool
    {
        $billable = $this->getBillableFromCustomerId($payload['customer_id']);

        if (!$billable) {
            logger()->error('No billable found for customer_id: ' . $payload['customer_id']);

            return false;
        }

        $subscription = $billable->subscriptions()->where('polar_id', $payload['id'])->first();
        if (!$subscription) {
            logger()->error('No subscription found for polar_id: ' . $payload['id']);

            return false;
        }

        $subscription->update([
            'status' => 'revoked',
            'ends_at' => now(),
        ]);

        event(new \Mafrasil\CashierPolar\Events\SubscriptionRevoked($subscription, $payload));

        return true;
    }

    protected function handleSubscriptionUpdated(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;

        $billable = $this->getBillableFromCustomerId($data['customer_id'] ?? null);

        if (!$billable) {
            logger()->error('No billable found for customer_id: ' . ($data['customer_id'] ?? 'null'));
            return false;
        }

        $subscription = $billable->subscriptions()->where('polar_id', $data['id'] ?? null)->first();
        if (!$subscription) {
            logger()->error('No subscription found for polar_id: ' . ($data['id'] ?? 'null'));
            return false;
        }

        // Update subscription status and details
        $subscription->update([
            'status' => $data['status'] ?? 'unknown',
            'current_period_start' => isset($data['current_period_start']) ? now()->parse($data['current_period_start']) : null,
            'current_period_end' => isset($data['current_period_end']) ? now()->parse($data['current_period_end']) : null,
            // Only set ends_at if cancel_at_period_end is true, and use current_period_end as the end date
            'ends_at' => ($data['cancel_at_period_end'] ?? false) ?
            (isset($data['current_period_end']) ? now()->parse($data['current_period_end']) : null) :
            null,
            'cancel_at_period_end' => $data['cancel_at_period_end'] ?? false,
        ]);

        // Update subscription item with new price information
        if ($item = $subscription->items()->first()) {
            $item->update([
                'price_id' => $data['price_id'],
                'price_currency' => $data['price']['currency'] ?? $data['currency'],
                'price_amount' => $data['price']['amount'] ?? $data['amount'],
                'recurring_interval' => $data['recurring_interval'],
                'product_name' => $data['product']['name'] ?? null,
                'product_description' => $data['product']['description'] ?? null,
            ]);
        }

        // Create transaction record if amount is present
        if (isset($data['amount']) || isset($data['price']['amount'])) {
            $billable->transactions()->create([
                'polar_id' => $data['id'] . '_' . now()->timestamp,
                'polar_subscription_id' => $data['id'],
                'status' => 'completed',
                'total' => $data['price']['amount'] ?? $data['amount'] ?? 0,
                'tax' => $data['tax_amount'] ?? 0,
                'currency' => $data['price']['currency'] ?? $data['currency'],
                'billed_at' => now(),
            ]);
        }

        event(new \Mafrasil\CashierPolar\Events\SubscriptionUpdated($subscription, $payload));

        return true;
    }

    protected function handleUnknownWebhook(array $payload): bool
    {
        logger()->info('Unknown webhook type received from Polar', $payload);

        return false;
    }

    protected function getBillableFromCustomerId(?string $customerId)
    {
        if (!$customerId) {
            logger()->error('Null customer_id provided to getBillableFromCustomerId', [
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            ]);

            return null;
        }

        $customer = PolarCustomer::where('polar_id', $customerId)->first();

        if (!$customer) {
            logger()->error('No Polar customer found for customer_id: ' . $customerId);

            return null;
        }

        $billable = $customer->billable;

        if (!$billable) {
            logger()->error('Billable relation not found for Polar customer: ' . $customerId);

            return null;
        }

        return $billable;
    }
}
