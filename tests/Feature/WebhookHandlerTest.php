<?php

use Illuminate\Support\Facades\Event;
use Mafrasil\CashierPolar\Events\CheckoutCreated;
use Mafrasil\CashierPolar\Events\CheckoutUpdated;
use Mafrasil\CashierPolar\Events\SubscriptionActive;
use Mafrasil\CashierPolar\Events\SubscriptionCanceled;
use Mafrasil\CashierPolar\Events\SubscriptionCreated;
use Mafrasil\CashierPolar\Events\SubscriptionRevoked;
use Mafrasil\CashierPolar\Events\SubscriptionUpdated;
use Mafrasil\CashierPolar\Tests\Fixtures\User;

beforeEach(function () {
    Event::fake();
    $this->user = User::factory()->create();
    $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);
});

function sendWebhook($payload)
{
    $timestamp = now()->timestamp;
    $webhookId = 'wh_' . substr(md5(uniqid()), 0, 16);
    $payloadJson = json_encode($payload);

    $secret = config('cashier-polar.webhook_secret');
    $toSign = "{$webhookId}.{$timestamp}.{$payloadJson}";
    $signature = base64_encode(pack('H*', hash_hmac('sha256', $toSign, base64_decode($secret))));

    return test()->withHeaders([
        'webhook-id' => $webhookId,
        'webhook-signature' => 'v1,' . $signature,
        'webhook-timestamp' => (string) $timestamp,
        'content-type' => 'application/json',
    ])->postJson('polar/webhook', $payload);
}

it('handles checkout created webhook', function () {
    $payload = [
        'type' => 'checkout.created',
        'id' => 'ch_123',
        'customer_id' => 'cus_123',
        'checkout_id' => 'chk_123',
        'status' => 'pending',
        'total' => 2999,
        'tax' => 499,
        'currency' => 'USD',
    ];

    $response = sendWebhook($payload);

    $response->assertSuccessful();
    Event::assertDispatched(CheckoutCreated::class);

    $this->assertDatabaseHas('polar_transactions', [
        'polar_id' => 'ch_123',
        'checkout_id' => 'chk_123',
        'status' => 'pending',
        'total' => 2999,
        'tax' => 499,
        'currency' => 'USD',
    ]);
});

it('handles checkout updated webhook', function () {
    // First create a checkout
    $this->user->transactions()->create([
        'polar_id' => 'ch_123',
        'checkout_id' => 'chk_123',
        'status' => 'pending',
        'total' => 2999,
        'tax' => 0,
        'currency' => 'USD',
        'billed_at' => now(),
    ]);

    $payload = [
        'type' => 'checkout.updated',
        'id' => 'ch_123',
        'customer_id' => 'cus_123',
        'checkout_id' => 'chk_123',
        'status' => 'completed',
    ];

    $response = sendWebhook($payload);

    $response->assertSuccessful();
    Event::assertDispatched(CheckoutUpdated::class);

    $this->assertDatabaseHas('polar_transactions', [
        'checkout_id' => 'chk_123',
        'status' => 'completed',
    ]);
});

it('handles subscription active webhook', function () {
    // First create a subscription
    $this->user->subscriptions()->create([
        'polar_id' => 'sub_123',
        'type' => 'default',
        'status' => 'pending',
    ]);

    $payload = [
        'type' => 'subscription.active',
        'id' => 'sub_123',
        'customer_id' => 'cus_123',
        'status' => 'active',
    ];

    $response = sendWebhook($payload);

    $response->assertSuccessful();
    Event::assertDispatched(SubscriptionActive::class);

    $this->assertDatabaseHas('polar_subscriptions', [
        'polar_id' => 'sub_123',
        'status' => 'active',
    ]);
});

it('handles subscription canceled webhook', function () {
    // First create a subscription
    $this->user->subscriptions()->create([
        'polar_id' => 'sub_123',
        'type' => 'default',
        'status' => 'active',
    ]);

    $payload = [
        'type' => 'subscription.canceled',
        'id' => 'sub_123',
        'customer_id' => 'cus_123',
        'status' => 'canceled',
    ];

    $response = sendWebhook($payload);

    $response->assertSuccessful();
    Event::assertDispatched(SubscriptionCanceled::class);

    $this->assertDatabaseHas('polar_subscriptions', [
        'polar_id' => 'sub_123',
        'status' => 'canceled',
    ]);

    $subscription = $this->user->subscriptions()->where('polar_id', 'sub_123')->first();
    expect($subscription->ends_at)->not->toBeNull();
});

it('handles subscription revoked webhook', function () {
    // First create a subscription
    $this->user->subscriptions()->create([
        'polar_id' => 'sub_123',
        'type' => 'default',
        'status' => 'active',
    ]);

    $payload = [
        'type' => 'subscription.revoked',
        'id' => 'sub_123',
        'customer_id' => 'cus_123',
        'status' => 'revoked',
    ];

    $response = sendWebhook($payload);

    $response->assertSuccessful();
    Event::assertDispatched(SubscriptionRevoked::class);

    $this->assertDatabaseHas('polar_subscriptions', [
        'polar_id' => 'sub_123',
        'status' => 'revoked',
    ]);

    $subscription = $this->user->subscriptions()->where('polar_id', 'sub_123')->first();
    expect($subscription->ends_at)->not->toBeNull();
});

it('handles subscription updated webhook', function () {
    // First create a subscription
    $this->user->subscriptions()->create([
        'polar_id' => 'sub_123',
        'type' => 'default',
        'status' => 'active',
    ]);

    $payload = [
        'type' => 'subscription.updated',
        'id' => 'sub_123',
        'customer_id' => 'cus_123',
        'status' => 'paused',
    ];

    $response = sendWebhook($payload);

    $response->assertSuccessful();
    Event::assertDispatched(SubscriptionUpdated::class);

    $this->assertDatabaseHas('polar_subscriptions', [
        'polar_id' => 'sub_123',
        'status' => 'paused',
    ]);
});

it('handles unknown webhook types gracefully', function () {
    $payload = [
        'type' => 'unknown.event',
        'id' => 'evt_123',
        'customer_id' => 'cus_123',
    ];

    $response = sendWebhook($payload);

    $response->assertSuccessful();
    Event::assertNotDispatched(CheckoutCreated::class);
    Event::assertNotDispatched(SubscriptionCreated::class);
});

it('handles missing customer gracefully', function () {
    $payload = [
        'type' => 'subscription.created',
        'id' => 'sub_123',
        'customer_id' => 'cus_nonexistent',
        'status' => 'active',
    ];

    $response = sendWebhook($payload);

    $response->assertSuccessful();
    Event::assertNotDispatched(SubscriptionCreated::class);

    $this->assertDatabaseMissing('polar_subscriptions', [
        'polar_id' => 'sub_123',
    ]);
});
