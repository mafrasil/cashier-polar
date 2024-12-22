<?php

use Mafrasil\CashierPolar\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('can create a polar customer', function () {
    $customer = $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);

    expect($customer->polar_id)->toBe('cus_123')
        ->and($customer->billable)->toBeInstanceOf(User::class)
        ->and($customer->billable->id)->toBe($this->user->id);

    $this->assertDatabaseHas('polar_customers', [
        'polar_id' => 'cus_123',
        'billable_id' => $this->user->id,
        'billable_type' => User::class,
    ]);
});

it('can retrieve a polar customer', function () {
    $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);

    $customer = $this->user->customer;

    expect($customer->polar_id)->toBe('cus_123')
        ->and($customer->billable)->toBeInstanceOf(User::class)
        ->and($customer->billable->id)->toBe($this->user->id);
});

it('can create a subscription', function () {
    $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);

    $subscription = $this->user->subscriptions()->create([
        'polar_id' => 'sub_123',
        'type' => 'default',
        'status' => 'active',
    ]);

    expect($subscription->polar_id)->toBe('sub_123')
        ->and($subscription->type)->toBe('default')
        ->and($subscription->status->value)->toBe('active')
        ->and($subscription->billable)->toBeInstanceOf(User::class)
        ->and($subscription->billable->id)->toBe($this->user->id);

    $this->assertDatabaseHas('polar_subscriptions', [
        'polar_id' => 'sub_123',
        'billable_id' => $this->user->id,
        'billable_type' => User::class,
        'type' => 'default',
        'status' => 'active',
    ]);
});

it('can check if user has an active subscription', function () {
    $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);

    expect($this->user->subscribed())->toBeFalse();

    $this->user->subscriptions()->create([
        'polar_id' => 'sub_123',
        'type' => 'default',
        'status' => 'active',
    ]);

    expect($this->user->subscribed())->toBeTrue();
});

it('can check if user has a specific subscription type', function () {
    $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);

    expect($this->user->subscribed('premium'))->toBeFalse();

    $this->user->subscriptions()->create([
        'polar_id' => 'sub_123',
        'type' => 'premium',
        'status' => 'active',
    ]);

    expect($this->user->subscribed('premium'))->toBeTrue()
        ->and($this->user->subscribed('basic'))->toBeFalse();
});

it('can check if subscription is canceled', function () {
    $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);

    $subscription = $this->user->subscriptions()->create([
        'polar_id' => 'sub_123',
        'type' => 'default',
        'status' => 'active',
    ]);

    expect($subscription->cancelled())->toBeFalse();
    expect($subscription->valid())->toBeTrue();
    expect($subscription->onGracePeriod())->toBeFalse();

    $subscription->update([
        'status' => 'canceled',
        'ends_at' => now()->addDays(5),
        'cancel_at_period_end' => true,
        'current_period_end' => now()->addDays(5),
    ]);

    expect($subscription->cancelled())->toBeTrue()
        ->and($subscription->valid())->toBeTrue()
        ->and($subscription->onGracePeriod())->toBeTrue();

    $subscription->update([
        'ends_at' => now()->subDay(),
    ]);

    expect($subscription->active())->toBeFalse();
});

it('can check if subscription is on grace period', function () {
    $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);

    $subscription = $this->user->subscriptions()->create([
        'polar_id' => 'sub_123',
        'type' => 'default',
        'status' => 'active',
    ]);

    expect($subscription->onGracePeriod())->toBeFalse();

    $subscription->update([
        'status' => 'canceled',
        'ends_at' => now()->addDays(5),
        'cancel_at_period_end' => true,
        'current_period_end' => now()->addDays(5),
    ]);

    expect($subscription->onGracePeriod())->toBeTrue();

    $subscription->update([
        'ends_at' => now()->subDay(),
        'current_period_end' => now()->subDay(),
    ]);

    expect($subscription->onGracePeriod())->toBeFalse();
});

it('can create a transaction', function () {
    $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);

    $transaction = $this->user->transactions()->create([
        'polar_id' => 'ch_123',
        'checkout_id' => 'chk_123',
        'status' => 'completed',
        'total' => 2999,
        'tax' => 499,
        'currency' => 'USD',
        'billed_at' => now(),
    ]);

    expect($transaction->polar_id)->toBe('ch_123')
        ->and($transaction->checkout_id)->toBe('chk_123')
        ->and($transaction->status)->toBe('completed')
        ->and((float) $transaction->total)->toBe(2999.00)
        ->and((float) $transaction->tax)->toBe(499.00)
        ->and($transaction->currency)->toBe('USD')
        ->and($transaction->billable)->toBeInstanceOf(User::class)
        ->and($transaction->billable->id)->toBe($this->user->id);

    $this->assertDatabaseHas('polar_transactions', [
        'polar_id' => 'ch_123',
        'checkout_id' => 'chk_123',
        'billable_id' => $this->user->id,
        'billable_type' => User::class,
        'status' => 'completed',
        'total' => 2999.00,
        'tax' => 499.00,
        'currency' => 'USD',
    ]);
});

it('can retrieve all transactions', function () {
    $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);

    $this->user->transactions()->create([
        'polar_id' => 'ch_123',
        'checkout_id' => 'chk_123',
        'status' => 'completed',
        'total' => 2999,
        'tax' => 499,
        'currency' => 'USD',
        'billed_at' => now(),
    ]);

    $this->user->transactions()->create([
        'polar_id' => 'ch_456',
        'checkout_id' => 'chk_456',
        'status' => 'completed',
        'total' => 1999,
        'tax' => 299,
        'currency' => 'USD',
        'billed_at' => now(),
    ]);

    $transactions = $this->user->transactions;

    expect($transactions)->toHaveCount(2)
        ->and($transactions->first()->polar_id)->toBe('ch_123')
        ->and($transactions->last()->polar_id)->toBe('ch_456');
});
