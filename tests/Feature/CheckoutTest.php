<?php

use Illuminate\Support\Facades\Http;
use Mafrasil\CashierPolar\Tests\Fixtures\User;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    $this->user->createCustomer([
        'polar_id' => 'cus_123',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);
});

it('can create a checkout session', function () {
    Http::fake([
        '*checkouts/custom*' => Http::response([
            'id' => 'checkout_123',
            'url' => 'https://checkout.polar.sh/123',
        ], 200),
    ]);

    $checkout = $this->user->checkout('price_123');

    expect($checkout)
        ->toHaveKey('id', 'checkout_123')
        ->toHaveKey('url');
});

it('can retrieve a checkout session', function () {
    Http::fake([
        '*checkouts/custom/checkout_123*' => Http::response([
            'id' => 'checkout_123',
            'status' => 'completed',
        ], 200),
    ]);

    $checkout = \Mafrasil\CashierPolar\Facades\CashierPolar::getCheckout('checkout_123');

    expect($checkout)
        ->toHaveKey('id', 'checkout_123')
        ->toHaveKey('status', 'completed');
});
