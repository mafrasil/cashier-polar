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

    public function __construct(protected array $payload) {}

    public function handle()
    {
        Log::info('Processing Polar Webhook', [
            'type' => $this->payload['type'],
            'payload' => $this->payload,
            'metadata' => $this->payload['data']['metadata'] ?? null,
            'customer_id' => $this->payload['data']['customer_id'] ?? null,
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

        $billable = $this->getBillableFromCustomerId(
            $data['customer_id'] ?? null,
            $data['metadata'] ?? null
        );

        if (! $billable && isset($data['metadata']['billable_id'], $data['metadata']['billable_type'])) {
            $billableType = $data['metadata']['billable_type'];
            $billable = $billableType::find($data['metadata']['billable_id']);

            if ($billable && isset($data['customer_id'])) {
                try {
                    $billable->getOrCreateCustomer([
                        'polar_id' => $data['customer_id'],
                        'name' => $data['customer_name'] ?? $billable->name ?? 'Unknown',
                        'email' => $data['customer_email'] ?? $billable->email ?? 'unknown@example.com',
                    ]);

                    $billable->refresh();
                    $billable->load('customer');
                } catch (\Exception $e) {
                    logger()->error('Customer creation failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }
        }

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.($data['customer_id'] ?? 'null').' or billable_id: '.($data['metadata']['billable_id'] ?? 'not provided'));

            return false;
        }

        $transaction = $billable->transactions()->create([
            'polar_id' => $data['id'],
            'checkout_id' => $data['id'],
            'status' => $data['status'],
            'total' => $data['total_amount'] ?? 0,
            'tax' => $data['tax_amount'] ?? 0,
            'currency' => $data['currency'] ?? 'usd',
            'billed_at' => now(),
        ]);

        event(new \Mafrasil\CashierPolar\Events\CheckoutCreated($transaction, $payload));

        return true;
    }

    protected function handleCheckoutUpdated(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;

        $billable = $this->getBillableFromCustomerId(
            $data['customer_id'] ?? null,
            $data['metadata'] ?? null
        );

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.($data['customer_id'] ?? 'null'));

            return false;
        }

        $transaction = $billable->transactions()->where('checkout_id', $data['id'] ?? null)->first();
        if (! $transaction) {
            logger()->error('No transaction found for checkout_id: '.($data['id'] ?? 'null'));

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

        $billable = $this->getBillableFromCustomerId(
            $data['customer_id'] ?? null,
            $data['metadata'] ?? null
        );

        if (! $billable && isset($data['metadata']['billable_id'], $data['metadata']['billable_type'])) {
            $billableType = $data['metadata']['billable_type'];
            $billable = $billableType::find($data['metadata']['billable_id']);

            if ($billable && isset($data['customer_id'])) {
                try {
                    $billable->getOrCreateCustomer([
                        'polar_id' => $data['customer_id'],
                        'name' => $data['customer']['name'] ?? $billable->name ?? 'Unknown',
                        'email' => $data['customer']['email'] ?? $billable->email ?? 'unknown@example.com',
                    ]);

                    $billable->refresh();
                    $billable->load('customer');
                } catch (\Exception $e) {
                    logger()->error('Customer creation failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }
        }

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.($data['customer_id'] ?? 'null'));

            return false;
        }

        $transaction = $billable->transactions()->create([
            'polar_id' => $data['id'],
            'polar_subscription_id' => $data['subscription_id'] ?? null,
            'checkout_id' => $data['checkout_id'] ?? null,
            'status' => 'completed',
            'total' => $data['amount'] ?? 0,
            'tax' => $data['tax_amount'] ?? 0,
            'currency' => $data['currency'] ?? 'usd',
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

            $billable = $this->resolveBillable($data);

            if (! $billable) {
                logger()->error('No billable found for customer_id: '.($data['customer_id'] ?? 'null'));

                return false;
            }

            $this->ensureCustomerExists($billable, $data);

            $subscription = $billable->subscriptions()->where('polar_id', $data['id'])->first();

            $subscriptionData = $this->buildSubscriptionData($data);

            if ($subscription) {
                $subscription->update($subscriptionData);
            } else {
                $subscriptionData['polar_id'] = $data['id'];
                $subscriptionData['type'] = 'default';
                $subscription = $billable->subscriptions()->create($subscriptionData);
            }

            $this->syncSubscriptionItems($subscription, $data);

            event(new \Mafrasil\CashierPolar\Events\SubscriptionCreated($subscription, $payload));

            return true;
        });
    }

    protected function handleSubscriptionActive(array $payload): bool
    {
        return DB::transaction(function () use ($payload) {
            $data = $payload['data'] ?? $payload;

            $billable = $this->resolveBillable($data);

            if (! $billable) {
                logger()->error('No billable found for customer_id: '.($data['customer_id'] ?? 'null'));

                return false;
            }

            $this->ensureCustomerExists($billable, $data);

            $subscription = $billable->subscriptions()->where('polar_id', $data['id'])->first();
            if (! $subscription) {
                logger()->error('No subscription found for polar_id: '.$data['id']);

                return false;
            }

            $subscription->update($this->buildSubscriptionData($data));

            $this->syncSubscriptionItems($subscription, $data);

            event(new \Mafrasil\CashierPolar\Events\SubscriptionActive($subscription, $payload));

            return true;
        });
    }

    protected function handleSubscriptionCanceled(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;

        $billable = $this->getBillableFromCustomerId(
            $data['customer_id'] ?? null,
            $data['metadata'] ?? null
        );

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.($data['customer_id'] ?? 'null'));

            return false;
        }

        $subscription = $billable->subscriptions()->where('polar_id', $data['id'] ?? null)->first();
        if (! $subscription) {
            logger()->error('No subscription found for polar_id: '.($data['id'] ?? 'null'));

            return false;
        }

        $subscription->update([
            'status' => SubscriptionStatus::CANCELED->value,
            'canceled_at' => isset($data['canceled_at']) ? now()->parse($data['canceled_at']) : now(),
            'cancel_at_period_end' => $data['cancel_at_period_end'] ?? true,
            'ends_at' => isset($data['ends_at'])
                ? now()->parse($data['ends_at'])
                : (isset($data['current_period_end'])
                    ? now()->parse($data['current_period_end'])
                    : now()),
            'customer_cancellation_reason' => $data['customer_cancellation_reason'] ?? null,
            'customer_cancellation_comment' => $data['customer_cancellation_comment'] ?? null,
        ]);

        event(new \Mafrasil\CashierPolar\Events\SubscriptionCanceled($subscription, $payload));

        return true;
    }

    protected function handleSubscriptionRevoked(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;

        $billable = $this->getBillableFromCustomerId(
            $data['customer_id'] ?? null,
            $data['metadata'] ?? null
        );

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.($data['customer_id'] ?? 'null'));

            return false;
        }

        $subscription = $billable->subscriptions()->where('polar_id', $data['id'] ?? null)->first();
        if (! $subscription) {
            logger()->error('No subscription found for polar_id: '.($data['id'] ?? 'null'));

            return false;
        }

        $subscription->update([
            'status' => SubscriptionStatus::CANCELED->value,
            'ended_at' => isset($data['ended_at']) ? now()->parse($data['ended_at']) : now(),
            'ends_at' => now(),
        ]);

        event(new \Mafrasil\CashierPolar\Events\SubscriptionRevoked($subscription, $payload));

        return true;
    }

    protected function handleSubscriptionUpdated(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;

        $billable = $this->resolveBillable($data);

        if (! $billable) {
            logger()->error('No billable found for customer_id: '.($data['customer_id'] ?? 'null'));

            return false;
        }

        $this->ensureCustomerExists($billable, $data);

        $subscription = $billable->subscriptions()->where('polar_id', $data['id'] ?? null)->first();
        if (! $subscription) {
            logger()->error('No subscription found for polar_id: '.($data['id'] ?? 'null'));

            return false;
        }

        $subscription->update($this->buildSubscriptionData($data));

        $this->syncSubscriptionItems($subscription, $data);

        event(new \Mafrasil\CashierPolar\Events\SubscriptionUpdated($subscription, $payload));

        return true;
    }

    protected function handleUnknownWebhook(array $payload): bool
    {
        logger()->info('Unknown webhook type received from Polar', $payload);

        return false;
    }

    protected function buildSubscriptionData(array $data): array
    {
        return [
            'status' => $data['status'] ?? 'incomplete',
            'trial_start' => isset($data['trial_start']) ? now()->parse($data['trial_start']) : null,
            'trial_end' => isset($data['trial_end']) ? now()->parse($data['trial_end']) : null,
            'ends_at' => isset($data['ends_at']) ? now()->parse($data['ends_at']) : null,
            'ended_at' => isset($data['ended_at']) ? now()->parse($data['ended_at']) : null,
            'canceled_at' => isset($data['canceled_at']) ? now()->parse($data['canceled_at']) : null,
            'current_period_start' => isset($data['current_period_start']) ? now()->parse($data['current_period_start']) : null,
            'current_period_end' => isset($data['current_period_end']) ? now()->parse($data['current_period_end']) : null,
            'started_at' => isset($data['started_at']) ? now()->parse($data['started_at']) : null,
            'cancel_at_period_end' => $data['cancel_at_period_end'] ?? false,
            'product_id' => $data['product_id'] ?? null,
            'discount_id' => $data['discount_id'] ?? null,
            'checkout_id' => $data['checkout_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'recurring_interval' => $data['recurring_interval'] ?? null,
            'recurring_interval_count' => $data['recurring_interval_count'] ?? null,
            'customer_cancellation_reason' => $data['customer_cancellation_reason'] ?? null,
            'customer_cancellation_comment' => $data['customer_cancellation_comment'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'custom_field_data' => $data['custom_field_data'] ?? null,
        ];
    }

    protected function syncSubscriptionItems($subscription, array $data): void
    {
        $prices = $data['prices'] ?? [];
        $product = $data['product'] ?? [];
        $status = $data['status'] ?? 'incomplete';

        foreach ($prices as $price) {
            $priceId = $price['id'] ?? null;
            if (! $priceId) {
                continue;
            }

            $item = $subscription->items()->where('price_id', $priceId)->first();

            $itemData = [
                'product_id' => $data['product_id'] ?? $price['product_id'] ?? null,
                'product_name' => $product['name'] ?? null,
                'product_description' => $product['description'] ?? null,
                'price_currency' => $price['price_currency'] ?? $data['currency'] ?? 'usd',
                'price_amount' => $price['price_amount'] ?? $data['amount'] ?? 0,
                'amount_type' => $price['amount_type'] ?? 'fixed',
                'recurring_interval' => $price['recurring_interval'] ?? $data['recurring_interval'] ?? null,
                'is_recurring' => $product['is_recurring'] ?? false,
                'status' => $status,
            ];

            if ($item) {
                $item->update($itemData);
            } else {
                $itemData['price_id'] = $priceId;
                $itemData['quantity'] = 1;
                $subscription->items()->create($itemData);
            }
        }

        // Remove items for prices no longer in the subscription
        $activePriceIds = collect($prices)->pluck('id')->filter()->toArray();
        if (! empty($activePriceIds)) {
            $subscription->items()->whereNotIn('price_id', $activePriceIds)->delete();
        }
    }

    protected function resolveBillable(array $data)
    {
        $billable = $this->getBillableFromCustomerId(
            $data['customer_id'] ?? null,
            $data['metadata'] ?? null
        );

        if (! $billable && isset($data['metadata']['billable_id'], $data['metadata']['billable_type'])) {
            $billableType = $data['metadata']['billable_type'];
            $billable = $billableType::find($data['metadata']['billable_id']);
        }

        return $billable;
    }

    protected function ensureCustomerExists($billable, array $data): void
    {
        if (! isset($data['customer_id'])) {
            return;
        }

        try {
            $billable->getOrCreateCustomer([
                'polar_id' => $data['customer_id'],
                'name' => $data['customer']['name'] ?? $billable->name ?? 'Unknown',
                'email' => $data['customer']['email'] ?? $billable->email ?? 'unknown@example.com',
            ]);

            $billable->refresh();
            $billable->load('customer');
        } catch (\Exception $e) {
            logger()->error('Customer creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function getBillableFromCustomerId(?string $customerId, ?array $metadata = null)
    {
        if ($metadata && isset($metadata['billable_id'], $metadata['billable_type'])) {
            $billableType = $metadata['billable_type'];
            $billable = $billableType::find($metadata['billable_id']);

            if ($billable) {
                return $billable;
            }
        }

        if ($customerId) {
            $customer = PolarCustomer::where('polar_id', $customerId)->first();

            if ($customer && $customer->billable) {
                return $customer->billable;
            }

            logger()->error('No valid billable found for Polar customer: '.$customerId);
        }

        return null;
    }
}
