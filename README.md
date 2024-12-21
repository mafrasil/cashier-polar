<p align="center"><img width="355" height="62" src="/art/logo.svg" alt="Logo Laravel Cashier"></p>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mafrasil/cashier-polar.svg?style=flat-square)](https://packagist.org/packages/mafrasil/cashier-polar)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mafrasil/cashier-polar/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mafrasil/cashier-polar/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mafrasil/cashier-polar/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mafrasil/cashier-polar/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mafrasil/cashier-polar.svg?style=flat-square)](https://packagist.org/packages/mafrasil/cashier-polar)

## Disclaimer

> **Note**: This is not an official Laravel package. This is a community-built package following Laravel Cashier principles and is currently a work in progress.

## Introduction

Cashier Polar provides an expressive, fluent interface to [Polar's](https://polar.sh) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing.

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

Generate a webhook secret:

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
]);

// Redirect to checkout
return redirect($checkout['url']);
```

### Access Subscriptions

```php
// Get subscription details
$subscription = $user->subscription;

echo $subscription->name;            // Get subscription name
echo $subscription->price;          // Get formatted price (e.g., "10.00 USD")
echo $subscription->interval;       // Get billing interval (e.g., "month")
echo $subscription->description;    // Get subscription description

// Check status
if ($subscription->valid()) {
    // Active, on trial, or on grace period
}

if ($subscription->active()) {
    // Currently active
}

// Trial handling
if ($subscription->onTrial()) {
    echo $subscription->trialEndDate();      // "2024-01-21"
    echo $subscription->daysUntilTrialEnds(); // Days remaining
}

// Cancellation handling
if ($subscription->cancelled()) {
    if ($subscription->onGracePeriod()) {
        echo $subscription->endDate();        // "2024-01-21"
        echo $subscription->daysUntilEnds();  // Days remaining
    }
}

// Check specific plan
if ($user->subscribedToPlan('premium')) {
    // Subscribed to premium plan
}
```

### Manage Subscriptions

```php
// Cancel
$subscription->cancel();     // End of period

// Resume (during grace period)
$subscription->resume();
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

### Listen for Events

```php
// EventServiceProvider.php
use Mafrasil\CashierPolar\Events\SubscriptionCreated;

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
