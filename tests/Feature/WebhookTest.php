<?php

use Illuminate\Support\Facades\Event;
use Mafrasil\CashierPolar\Events\SubscriptionCreated;
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

it('handles subscription created webhook', function () {
    $payload = [
        'type' => 'subscription.created',
        'id' => 'sub_123',
        'customer_id' => 'cus_123',
        'status' => 'active',
    ];

    $timestamp = now()->timestamp;
    $webhookId = 'wh_123';
    $payloadJson = json_encode($payload);

    // Create signature following the library's approach
    $secret = config('cashier-polar.webhook_secret');
    $toSign = "{$webhookId}.{$timestamp}.{$payloadJson}";
    $signature = base64_encode(pack('H*', hash_hmac('sha256', $toSign, base64_decode($secret))));

    $response = $this->withHeaders([
        'webhook-id' => $webhookId,
        'webhook-signature' => 'v1,' . $signature,
        'webhook-timestamp' => (string) $timestamp,
        'content-type' => 'application/json',
    ])->postJson('polar/webhook', $payload);

    $response->assertSuccessful();

    Event::assertDispatched(SubscriptionCreated::class);

    $this->assertDatabaseHas('polar_subscriptions', [
        'polar_id' => 'sub_123',
        'status' => 'active',
    ]);
});
