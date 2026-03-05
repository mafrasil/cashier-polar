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

    public function createCheckout(string $productId, array $options = [])
    {
        $payload = array_merge([
            'products' => [$productId],
        ], $options);

        $response = $this->request()->post('checkouts/', $payload);

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

    public function revokeSubscription(string $subscription_id, ?string $reason = null, ?string $comment = null)
    {
        $payload = ['revoke' => true];

        if ($reason) {
            $payload['customer_cancellation_reason'] = $reason;
        }

        if ($comment) {
            $payload['customer_cancellation_comment'] = $comment;
        }

        return $this->request()
            ->patch("subscriptions/{$subscription_id}", $payload)
            ->json();
    }

    public function endTrial(string $subscription_id)
    {
        return $this->request()
            ->patch("subscriptions/{$subscription_id}", [
                'trial_end' => 'now',
            ])
            ->json();
    }

    public function getCheckout(string $checkoutId)
    {
        return $this->request()
            ->get("checkouts/{$checkoutId}")
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

    public function updateSubscription(string $subscription_id, string $productId, ?string $priceId = null)
    {
        $payload = ['product_id' => $productId];

        if ($priceId) {
            $payload['price_id'] = $priceId;
        }

        return $this->request()
            ->patch("subscriptions/{$subscription_id}", $payload)
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

    public function createCustomerSession(string $customerId)
    {
        return $this->request()
            ->post('customer-sessions/', [
                'customer_id' => $customerId,
            ])
            ->json();
    }

    protected function customerPortalRequest(string $sessionToken)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$sessionToken,
            'Content-Type' => 'application/json',
        ])->baseUrl($this->baseUrl());
    }

    public function generateOrderInvoice(string $orderId, string $sessionToken)
    {
        return $this->customerPortalRequest($sessionToken)
            ->post("customer-portal/orders/{$orderId}/invoice");
    }

    public function getOrderInvoice(string $orderId, string $sessionToken)
    {
        return $this->customerPortalRequest($sessionToken)
            ->get("customer-portal/orders/{$orderId}/invoice")
            ->json();
    }

    public function getCustomerPortalOrders(string $sessionToken, array $filters = [])
    {
        return $this->customerPortalRequest($sessionToken)
            ->get('customer-portal/orders/', $filters)
            ->json();
    }

    public function getSubscription(string $subscriptionId)
    {
        return $this->request()
            ->get("subscriptions/{$subscriptionId}")
            ->json();
    }
}
