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

Create a checkout session for a user:

```php
// Through the billable model (recommended)
$checkout = $user->checkout('price_id');

// The checkout method will automatically:
// - Add the customer_id from the user's Polar customer
// - Set the success_url from config (defaults to '/dashboard')

// Or with custom options
$checkout = $user->checkout('price_id', [
    'success_url' => route('checkout.success'),
    'cancel_url' => route('checkout.cancel'),
    // Any other Polar checkout options
]);

// The response will contain:
// - id: The checkout session ID
// - url: The checkout URL to redirect to
```

#### Using with Blade

```php
// Controller
public function checkout()
{
    // Make sure the user has a Polar customer ID first
    if (! $user->customer) {
        $user->createCustomer([
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    $checkout = auth()->user()->checkout('price_id');
    return view('checkout', ['checkoutUrl' => $checkout['url']]);
}

// View
<a href="{{ $checkoutUrl }}" class="button">Subscribe</a>
```

#### Using with API

```php
// routes/api.php
Route::post('checkout', function (Request $request) {
    $user = $request->user();

    // Ensure user has a Polar customer
    if (! $user->customer) {
        $user->createCustomer([
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    $checkout = $user->checkout('price_id');
    return ['url' => $checkout['url']];
});

// Fetch from any frontend
fetch('/api/checkout', { method: 'POST' })
    .then(res => res.json())
    .then(data => window.location.href = data.url);
```

#### Using with Separate Frontend (e.g., Next.js)

1. First, create an API endpoint to list products/prices:

```php
// routes/api.php
use Mafrasil\CashierPolar\Facades\CashierPolar;

Route::get('products', function () {
    return CashierPolar::products();
});

// Or in a controller
public function products()
{
    return CashierPolar::products();
}

// You can also get a specific product
$product = CashierPolar::product('product_id');

// Or filter products
$products = CashierPolar::products([
    'is_archived' => false,
    // other filters
]);
```

2. Fetch and display products in Next.js:

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

## Local Development

To test this package locally:

1. Create a workspace directory and clone both repositories:

```bash
# Create and enter workspace directory
mkdir cashier-polar-workspace
cd cashier-polar-workspace

# Clone your package
git clone https://github.com/mafrasil/cashier-polar.git

# Create a new Laravel project for testing
laravel new test-project
cd test-project
```

2. Add the local repository to your test project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../cashier-polar"
        }
    ]
}
```

3. Require the package in your test project:

```bash
composer require mafrasil/cashier-polar:*
```

4. Set up your test project:

```bash
# Publish and run migrations
php artisan vendor:publish --tag="cashier-polar-migrations"
php artisan migrate

# Generate webhook secret
php artisan cashier-polar:webhook-secret
```

Your workspace structure will look like this:

```
cashier-polar-workspace/
├── cashier-polar/         # Your package
└── test-project/         # Laravel test project
```

Any changes you make to the package will be automatically reflected in your test project.

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
