<?php

namespace Mafrasil\CashierPolar;

use Illuminate\Support\Facades\Http;

class CashierPolar
{
    protected function baseUrl()
    {
        return config('cashier-polar.urls.'.
            (config('cashier-polar.sandbox') ? 'sandbox' : 'production'));
    }

    protected function request()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.config('cashier-polar.key'),
            'Content-Type' => 'application/json',
        ])->baseUrl($this->baseUrl());
    }

    public function createCheckout(string $priceId, array $options = [])
    {
        $response = $this->request()->post('checkouts/custom/', array_merge([
            'organization_id' => config('cashier-polar.organization_id'),
            'product_price_id' => $priceId,
            'payment_processor' => 'stripe',
        ], $options));

        return $response->json();
    }

    public function getCheckout(string $checkoutId)
    {
        return $this->request()
            ->get("checkouts/custom/{$checkoutId}")
            ->json();
    }

    public function getProducts()
    {
        return $this->request()
            ->get('products', [
                'organization_id' => config('cashier-polar.organization_id'),
                'is_archived' => false,
            ])
            ->json();
    }
}
