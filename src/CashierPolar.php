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

    public function resumeSubscription(string $subscription_id)
    {
        return $this->request()
            ->patch("subscriptions/{$subscription_id}", [
                'cancel_at_period_end' => false,
            ])
            ->json();
    }

    public function cancelSubscription(string $subscription_id)
    {
        return $this->request()
            ->patch("subscriptions/{$subscription_id}", [
                'cancel_at_period_end' => true,
            ])
            ->json();
    }

    public function revokeSubscription(string $subscription_id)
    {
        return $this->request()
            ->delete("subscriptions/{$subscription_id}")
            ->json();
    }

    public function getCheckout(string $checkoutId)
    {
        return $this->request()
            ->get("checkouts/custom/{$checkoutId}")
            ->json();
    }

    public function products(array $filters = [])
    {
        return $this->request()
            ->get('products', array_merge([
                'organization_id' => config('cashier-polar.organization_id'),
                'is_archived' => false,
            ], $filters))
            ->json();
    }

    public function product(string $productId)
    {
        return $this->request()
            ->get("products/{$productId}", [
                'organization_id' => config('cashier-polar.organization_id'),
            ])
            ->json();
    }

    public function updateSubscription(string $subscription_id, string $price_id)
    {
        return $this->request()
            ->patch("subscriptions/{$subscription_id}", [
                'product_price_id' => $price_id,
            ])
            ->json();
    }

    public function getOrders(array $filters = [])
    {
        if (empty($filters['customer_id'])) {
            throw new \InvalidArgumentException('customer_id is required to fetch orders');
        }

        return $this->request()
            ->get('orders/', array_merge([
                'organization_id' => config('cashier-polar.organization_id'),
                'customer_id' => $filters['customer_id'],
            ], $filters))
            ->json();
    }

    public function getOrderInvoice(string $orderId)
    {
        return $this->request()
            ->get("customer-portal/orders/{$orderId}/invoice")
            ->json();
    }
}
