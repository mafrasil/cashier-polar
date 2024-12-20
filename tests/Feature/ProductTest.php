<?php

use Illuminate\Support\Facades\Http;
use Mafrasil\CashierPolar\Facades\CashierPolar;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('can list products', function () {
    Http::fake([
        '*products*' => Http::response([
            [
                'id' => 'prod_123',
                'name' => 'Basic Plan',
                'description' => 'Basic subscription plan',
                'prices' => [
                    [
                        'id' => 'price_123',
                        'amount' => 999,
                        'currency' => 'USD',
                        'interval' => 'month',
                    ],
                ],
            ],
            [
                'id' => 'prod_456',
                'name' => 'Premium Plan',
                'description' => 'Premium subscription plan',
                'prices' => [
                    [
                        'id' => 'price_456',
                        'amount' => 1999,
                        'currency' => 'USD',
                        'interval' => 'month',
                    ],
                ],
            ],
        ], 200),
    ]);

    $products = CashierPolar::products();

    expect($products)->toBeArray()->toHaveCount(2);

    // Test first product
    expect($products[0])
        ->toHaveKey('id', 'prod_123')
        ->toHaveKey('name', 'Basic Plan')
        ->toHaveKey('prices');

    expect($products[0]['prices'])
        ->toBeArray()
        ->toHaveCount(1);

    // Test second product
    expect($products[1])
        ->toHaveKey('id', 'prod_456')
        ->toHaveKey('name', 'Premium Plan')
        ->toHaveKey('prices');

    expect($products[1]['prices'])
        ->toBeArray()
        ->toHaveCount(1);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/products') &&
        $request['organization_id'] === config('cashier-polar.organization_id') &&
            $request['is_archived'] === false;
    });
});

it('returns empty array when no products exist', function () {
    Http::fake([
        '*products*' => Http::response([], 200),
    ]);

    $products = CashierPolar::products();

    expect($products)
        ->toBeArray()
        ->toBeEmpty();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/products') &&
        $request['organization_id'] === config('cashier-polar.organization_id');
    });
});

it('can filter products', function () {
    Http::fake([
        '*products*' => Http::response([
            [
                'id' => 'prod_123',
                'name' => 'Basic Plan',
                'is_archived' => false,
            ],
        ], 200),
    ]);

    $products = CashierPolar::products([
        'is_archived' => true,
        'custom_filter' => 'value',
    ]);

    Http::assertSent(function ($request) {
        return $request['is_archived'] === true &&
            $request['custom_filter'] === 'value';
    });
});

it('can get a single product', function () {
    Http::fake([
        '*products/prod_123*' => Http::response([
            'id' => 'prod_123',
            'name' => 'Basic Plan',
            'description' => 'Basic subscription plan',
            'prices' => [
                [
                    'id' => 'price_123',
                    'amount' => 999,
                    'currency' => 'USD',
                    'interval' => 'month',
                ],
            ],
        ], 200),
    ]);

    $product = CashierPolar::product('prod_123');

    expect($product)
        ->toBeArray()
        ->toHaveKey('id', 'prod_123')
        ->toHaveKey('name', 'Basic Plan')
        ->toHaveKey('prices');

    expect($product['prices'])
        ->toBeArray()
        ->toHaveCount(1);

    expect($product['prices'][0])
        ->toHaveKey('id', 'price_123')
        ->toHaveKey('amount', 999)
        ->toHaveKey('currency', 'USD');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/products/prod_123') &&
        $request['organization_id'] === config('cashier-polar.organization_id');
    });
});

it('handles product not found', function () {
    Http::fake([
        '*products/prod_123*' => Http::response([
            'error' => 'Product not found',
        ], 404),
    ]);

    $product = CashierPolar::product('prod_123');

    expect($product)
        ->toBeArray()
        ->toHaveKey('error', 'Product not found');
});
