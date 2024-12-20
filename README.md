# Laravel Cashier Polar

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mafrasil/cashier-polar.svg?style=flat-square)](https://packagist.org/packages/mafrasil/cashier-polar)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mafrasil/cashier-polar/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mafrasil/cashier-polar/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mafrasil/cashier-polar/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mafrasil/cashier-polar/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mafrasil/cashier-polar.svg?style=flat-square)](https://packagist.org/packages/mafrasil/cashier-polar)

A Laravel Cashier integration for [Polar](https://polar.sh) payment processing. This package provides an expressive interface for subscription billing services using Polar's API.

## Features

-   Easy integration with Polar's payment processing
-   Subscription management
-   Webhook handling for various events
-   Customer management
-   Transaction tracking
-   Support for both production and sandbox environments

## Requirements

-   PHP 8.3+
-   Laravel 10.0+ or 11.0+
-   Polar account and API credentials

## Installation

You can install the package via composer:

```bash
composer require mafrasil/cashier-polar
```

After installing the package, publish and run the migrations:

```bash
php artisan vendor:publish --tag="cashier-polar-migrations"
php artisan migrate
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="cashier-polar-config"
```

## Configuration

Configure your Polar credentials in your `.env` file:

```env
POLAR_API_KEY=your-api-key
POLAR_ORGANIZATION_ID=your-organization-id
POLAR_WEBHOOK_SECRET=your-webhook-secret
POLAR_SANDBOX=true # Set to false for production
```

You can generate a secure webhook secret using the provided artisan command:

```bash
php artisan cashier-polar:webhook-secret
```

This will generate a cryptographically secure secret that you can use for your webhook configuration.

## Usage

### Setup Billable Model

Add the `Billable` trait to your billable model (typically the User model):

```php
use Mafrasil\CashierPolar\Concerns\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

### Customer Management

Create a customer:

```php
$customer = $user->createCustomer([
    'polar_id' => 'cus_123',
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
```

Retrieve a customer:

```php
$customer = $user->customer;
```

### Checkout Sessions

Create a checkout session:

```php
use Mafrasil\CashierPolar\Facades\CashierPolar;

$checkout = CashierPolar::createCheckout('price_id', [
    // Additional options
]);
```

#### Using with Blade

```php
// Controller
public function checkout()
{
    $checkout = CashierPolar::createCheckout('price_id');
    return view('checkout', ['checkoutUrl' => $checkout['url']]);
}

// View
<a href="{{ $checkoutUrl }}" class="button">Subscribe</a>
```

#### Using with API

```php
// routes/api.php
Route::post('checkout', function () {
    $checkout = CashierPolar::createCheckout('price_id');
    return ['url' => $checkout['url']];
});

// Fetch from any frontend
fetch('/api/checkout', { method: 'POST' })
    .then(res => res.json())
    .then(data => window.location.href = data.url);
```

### Subscriptions

Access subscriptions:

```php
// Get all subscriptions
$subscriptions = $user->subscriptions;

// Get the default subscription
$subscription = $user->subscription();

// Check subscription status
if ($subscription->valid()) {
    // Subscription is valid
}

if ($subscription->active()) {
    // Subscription is active
}

if ($subscription->canceled()) {
    // Subscription is canceled
}
```

### Transactions

Access transactions:

```php
// Get all transactions
$transactions = $user->transactions;
```

### Webhook Handling

The package automatically handles the following webhook events:

-   `checkout.created`
-   `checkout.updated`
-   `subscription.created`
-   `subscription.updated`
-   `subscription.active`
-   `subscription.revoked`
-   `subscription.canceled`

## Events

The package dispatches Laravel events for various webhook events:

-   `CheckoutCreated`
-   `CheckoutUpdated`
-   `SubscriptionCreated`
-   `SubscriptionUpdated`
-   `SubscriptionActive`
-   `SubscriptionRevoked`
-   `SubscriptionCanceled`

### Listening to Events

```php
// EventServiceProvider.php
Event::listen(function (SubscriptionCreated $event) {
    $subscription = $event->subscription;
    $user = $subscription->billable;

    // Send welcome email, etc.
});
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [mafrasil](https://github.com/mafrasil)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
