<?php

namespace Mafrasil\CashierPolar\Tests\Feature\Integration;

use Illuminate\Support\Facades\Http;
use Mafrasil\CashierPolar\Facades\CashierPolar;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('can list products', function () {
    Http::fake([
        '*products*' => Http::response([
            'items' => [
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
            ],
            'pagination' => [
                'total_count' => 2,
                'max_page' => 1,
            ],
        ], 200),
    ]);

    $products = CashierPolar::products();

    expect($products['items'])->toBeArray()
        ->and($products['items'][0])
        ->toHaveKey('id', 'prod_123')
        ->toHaveKey('name', 'Basic Plan')
        ->toHaveKey('prices');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/products') &&
        $request['organization_id'] === config('cashier-polar.organization_id') &&
            $request['is_archived'] === false;
    });
});

it('returns empty array when no products exist', function () {
    Http::fake([
        '*products*' => Http::response([
            'items' => [],
            'pagination' => [
                'total_count' => 0,
                'max_page' => 1,
            ],
        ], 200),
    ]);

    $products = CashierPolar::products();

    expect($products['items'])
        ->toBeArray()
        ->toBeEmpty();
});

it('can filter products', function () {
    Http::fake([
        '*products*' => Http::response([
            'items' => [
                [
                    'id' => 'prod_123',
                    'name' => 'Basic Plan',
                    'is_archived' => false,
                ],
            ],
            'pagination' => [
                'total_count' => 1,
                'max_page' => 1,
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

it('can get real products from sandbox', function () {
    $this->allowRealRequests();

    $response = CashierPolar::products();

    expect($response)
        ->toBeArray();

    // If there's an error in the response, log it but don't fail
    if (isset($response['error'])) {
        $this->markTestSkipped("API Error: {$response['error']} - ".json_encode($response['detail'] ?? []));
    }

    // Test the response structure
    expect($response)
        ->toHaveKey('items')
        ->toHaveKey('pagination');

    expect($response['pagination'])
        ->toHaveKey('total_count')
        ->toHaveKey('max_page');

    if (! empty($response['items'])) {
        expect($response['items'][0])
            ->toHaveKey('id')
            ->toHaveKey('name')
            ->toHaveKey('prices');
    }
})->group('integration');

it('can get a real single product from sandbox', function () {
    $this->allowRealRequests();

    $response = CashierPolar::products();

    // Handle API errors
    if (isset($response['error'])) {
        $this->markTestSkipped("API Error: {$response['error']} - ".json_encode($response['detail'] ?? []));
    }

    if (empty($response['items'])) {
        $this->markTestSkipped('No products available in sandbox to test with');
    }

    $firstProduct = $response['items'][0];
    $productResponse = CashierPolar::product($firstProduct['id']);

    // Handle API errors for single product request
    if (isset($productResponse['error'])) {
        $this->markTestSkipped("API Error: {$productResponse['error']} - ".json_encode($productResponse['detail'] ?? []));
    }

    expect($productResponse)
        ->toBeArray()
        ->toHaveKey('id', $firstProduct['id'])
        ->toHaveKey('name')
        ->toHaveKey('prices');
})->group('integration');
