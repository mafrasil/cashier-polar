<p align="center"><img width="355" height="62" src="/art/logo.svg" alt="Logo Laravel Cashier"></p>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mafrasil/cashier-polar.svg?style=flat-square)](https://packagist.org/packages/mafrasil/cashier-polar)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mafrasil/cashier-polar/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mafrasil/cashier-polar/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mafrasil/cashier-polar/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mafrasil/cashier-polar/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mafrasil/cashier-polar.svg?style=flat-square)](https://packagist.org/packages/mafrasil/cashier-polar)

## Disclaimer

> **Note**: This is not an official Laravel package. This is a community-built package following Laravel Cashier principles.

## Introduction

Cashier Polar provides an expressive, fluent interface to [Polar's](https://polar.sh) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing.

## Table of Contents

-   [Requirements](#requirements)
-   [Installation](#installation)
-   [Configuration](#configuration)
-   [Basic Usage](#basic-usage)
    -   [Setup Billable Model](#setup-billable-model)
    -   [Create a Checkout Session](#create-a-checkout-session)
    -   [Access Subscriptions](#access-subscriptions)
    -   [Manage Subscriptions](#manage-subscriptions)
    -   [Trials](#trials)
    -   [Products and Pricing](#products-and-pricing)
    -   [Orders and Invoices](#orders-and-invoices)
    -   [Webhook Events](#webhook-events)
    -   [Listen for Events](#listen-for-events)
-   [Testing](#testing)
-   [Credits](#credits)
-   [License](#license)

## Requirements

-   PHP 8.3+
-   Laravel 10.0+ / 11.0+
-   Polar account and API credentials (https://polar.sh)

## Installation

```bash
composer require mafrasil/cashier-polar

php artisan vendor:publish --tag="cashier-polar-migrations"
php artisan migrate

php artisan vendor:publish --tag="cashier-polar-config"
```

## Configuration

```env
POLAR_API_KEY=your-api-key
POLAR_ORGANIZATION_ID=your-organization-id
POLAR_WEBHOOK_SECRET=your-webhook-secret
POLAR_SANDBOX=true # Set to false for production
```

Generate a webhook secret or grab it from your Polar Webhook settings:

```bash
php artisan cashier-polar:webhook-secret
```

## Basic Usage

### Setup Billable Model

```php
use Mafrasil\CashierPolar\Concerns\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

### Create a Checkout Session

```php
// Simple checkout with a product ID
$checkout = $user->checkout('product_id');

// With options
$checkout = $user->checkout('product_id', [
    'success_url' => route('checkout.success'),
    'metadata' => [
        'custom_field' => 'value'
    ]
]);

// Redirect to checkout
return redirect($checkout['url']);
```

### Access Subscriptions

```php
// Get subscription details
$subscription = $user->subscription;

echo $subscription->name;            // Product name (e.g., "Pro")
echo $subscription->price;           // Formatted price (e.g., "15.00 USD")
echo $subscription->interval;        // Billing interval (e.g., "month")
echo $subscription->description;     // Product description
echo $subscription->product_id;      // Polar product ID

// Check subscription status
if ($subscription->active()) { // or $user->subscribed()
    // Subscription is usable if any of:
    // - Status is active or trialing
    // - Cancelled but on trial or grace period
}

// Subscription has been cancelled and on grace period (within billing period)
if ($subscription->onGracePeriod()) {}

// Subscription has been cancelled and grace period has ended
if ($subscription->cancelled()) {}

// Subscription has ended
if ($subscription->ended()) {}

// Period information
echo $subscription->currentPeriod();  // "2024-01-01 to 2024-02-01"
if ($subscription->withinPeriod()) {}

// Check specific product
if ($subscription->hasProduct('product_id')) {}

// Check specific price plan
if ($subscription->hasPlan('price_id')) {}

// Payment issues
if ($subscription->hasPaymentIssue()) {}
```

### Manage Subscriptions

```php
// Cancel at end of billing period
$subscription->cancel();

// Resume a cancelled subscription (before period ends)
$subscription->resume();

// Revoke immediately (cancel right now)
$subscription->revoke();

// Change to a different product
$subscription->change('new_product_id');

// Example usage
if ($subscription->onGracePeriod()) {
    echo "Subscription will cancel on " . $subscription->ends_at->format('Y-m-d');
} elseif ($subscription->cancelled()) {
    echo "Subscription has been cancelled";
} else {
    echo "Active " . $subscription->interval . " subscription";
}
```

### Trials

Trials are configured on your products in the Polar dashboard. When a customer subscribes to a product with a trial, the subscription will have `trialing` status.

```php
// Check if subscription is on trial
if ($subscription->onTrial()) {
    echo "Trial ends on " . $subscription->trialEndDate(); // "2024-01-15"
    echo "Days left: " . $subscription->days_until_trial_ends;
}

// Check if trial has expired
if ($subscription->hasExpiredTrial()) {}

// End trial early and start paying immediately
$subscription->endTrial();

// Cancel during trial (won't convert to paid when trial ends)
$subscription->cancel();

// Revoke during trial (ends immediately)
$subscription->revoke();

// Trial dates
$subscription->trialStart();    // Carbon instance
$subscription->trialEnd();      // Carbon instance
$subscription->trialEndDate();  // "2024-01-15"
```

The `active()` method returns `true` for trialing subscriptions, so `$user->subscribed()` works seamlessly whether the user is on a trial or a paid plan.

### Products and Pricing

```php
use Mafrasil\CashierPolar\Facades\CashierPolar;

// Get all products
$products = CashierPolar::products();

// Get specific product
$product = CashierPolar::product('product_id');

// Get products with filters
$products = CashierPolar::products([
    'is_archived' => true,
    // other filters...
]);
```

### Orders and Invoices

In Polar, orders represent payments/invoices. Each time a customer pays (initial purchase, subscription renewal, plan change), an order is created.

```php
// Get paid invoices via the customer portal API
// Returns only paid orders with rich data: product, subscription,
// line items, invoice_number, is_invoice_generated, billing_reason, etc.
$invoices = $user->invoices();

// Filter invoices
$invoices = $user->invoices([
    'product_id' => 'product_id',
    'product_billing_type' => 'recurring', // or 'one_time'
    'subscription_id' => 'subscription_id',
    'query' => 'Pro Plan',
    'page' => 1,
    'limit' => 10,
    'sorting' => ['-created_at'],
]);

// Get all orders via the server-side API (includes all statuses)
$orders = $user->orders();

// Generate an invoice PDF for an order (must be called before retrieving)
// This triggers Polar to create the invoice PDF — returns 202 (accepted)
$user->generateInvoice('order_id');

// Get invoice PDF URL for a specific order
// The invoice must be generated first, otherwise this returns null
$invoiceUrl = $user->getInvoice('order_id');

// Access local transaction records (stored via webhooks)
$transactions = $user->transactions()->get();

// Only completed/paid local transactions
$transactions = $user->transactions()->paid()->get();
```

> **Important**: Polar requires invoices to be generated before they can be retrieved. Call `generateInvoice()` first, wait a few seconds, then call `getInvoice()`. For a reliable approach, listen to the `order.updated` webhook and check the `is_invoice_generated` field before retrieving.

### Webhook Events

The package automatically handles these webhook events:

-   `checkout.created`
-   `checkout.updated`
-   `order.created`
-   `subscription.created`
-   `subscription.updated`
-   `subscription.active`
-   `subscription.revoked`
-   `subscription.canceled`

#### Webhook Configuration

By default, Cashier Polar listens for webhooks at `/webhooks/polar`. You can customize this path in your `config/cashier-polar.php` configuration file:

```php
'path' => 'custom/webhook/path'
```

Make sure to configure your webhook URL in your Polar dashboard to match your application's webhook endpoint:

-   URL: `https://your-domain.com/webhooks/polar` (or your custom path)
-   Secret: Use the value from your `POLAR_WEBHOOK_SECRET` environment variable

The package automatically validates webhook signatures to ensure they come from Polar.

#### Local Testing with Ngrok

For local development, you can use [Ngrok](https://ngrok.com) to create a secure tunnel to your local application:

```bash
ngrok http 8000
```

Then use the generated Ngrok URL (e.g., `https://random-string.ngrok.io/webhooks/polar`) as your webhook endpoint in the Polar dashboard.

### Listen for Events

```php
use Mafrasil\CashierPolar\Events\SubscriptionCreated;
use Mafrasil\CashierPolar\Events\SubscriptionCanceled;
use Mafrasil\CashierPolar\Events\OrderCreated;

Event::listen(function (SubscriptionCreated $event) {
    $subscription = $event->subscription;
    $user = $subscription->billable;
    // Handle event
});
```

## Testing

```bash
composer test
```

## Credits

-   [mafrasil](https://github.com/mafrasil)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
