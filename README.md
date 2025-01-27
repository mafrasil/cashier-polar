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
// Simple checkout
$checkout = $user->checkout('price_id');

// With options
$checkout = $user->checkout('price_id', [
    'success_url' => route('checkout.success'),
    'cancel_url' => route('checkout.cancel'),
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

echo $subscription->name;            // Get subscription name
echo $subscription->price;           // Get formatted price (e.g., "10.00 USD")
echo $subscription->interval;        // Get billing interval (e.g., "month")
echo $subscription->description;     // Get subscription description

// Check subscription status
if ($subscription->active()) { // or $user->subscribed()
    // Subscription is usable if any of:
    // - Status is active
    // - Currently on grace period after cancellation
}

// Subscription has been cancelled and on grace period (within billing period)
if ($subscription->onGracePeriod()) {}

// Subscription has been cancelled and grace period has ended (outside billing period)
if ($subscription->cancelled()) {}

// Period information
echo $subscription->currentPeriod();  // "2024-01-01 to 2024-02-01"
if ($subscription->withinPeriod()) {}
```

### Manage Subscriptions

```php
$subscription->cancel();                    // Cancel subscription (End of period)
$subscription->resume();                    // Resume subscription if cancelled

$subscription->revoke();                    // Revoke subscription (cancel immediately)

$subscription->change('new_price_id');      // Change subscription plan
$subscription->onGracePeriod();             // Check if scheduled for cancellation
$subscription->cancelled();                 // Check if cancelled
$subscription->active();                    // Check if subscription is active

// Example usage
if ($subscription->onGracePeriod()) {
    echo "Subscription will cancel on " . $subscription->ends_at->format('Y-m-d');
} elseif ($subscription->cancelled()) {
    echo "Subscription has been cancelled";
} else {
    echo "Active " . $subscription->interval . " subscription";
}
```

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

```php
// Get all orders for a user
$orders = $user->orders();

// Get orders with filters
$orders = $user->orders([
    'status' => 'completed',
    // other filters...
]);

// Get invoice URL for specific order
$invoiceUrl = $user->getInvoice('order_id');
```

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

The package automatically validates webhook signatures to ensure they come from Polar and logs all incoming webhook data for debugging purposes.

#### Local Testing with Ngrok

For local development, you can use [Ngrok](https://ngrok.com) to create a secure tunnel to your local application:

```bash
ngrok http 8000
```

Then use the generated Ngrok URL (e.g., `https://random-string.ngrok.io/webhooks/polar`) as your webhook endpoint in the Polar dashboard. This allows you to receive and test webhooks during local development.

### Listen for Events

```php
// EventServiceProvider.php
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
