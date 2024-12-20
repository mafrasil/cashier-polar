<?php

namespace Mafrasil\CashierPolar\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Http;
use Mafrasil\CashierPolar\CashierPolarServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load .env.testing if it exists, fallback to .env
        if (file_exists(__DIR__ . '/../.env.testing')) {
            \Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.testing')->load();
        } elseif (file_exists(__DIR__ . '/../.env')) {
            \Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();
        }

        Factory::guessFactoryNamesUsing(
            fn(string $modelName) => 'Mafrasil\\CashierPolar\\Tests\\Fixtures\\' . class_basename($modelName) . 'Factory'
        );

        // By default, prevent stray requests
        Http::preventStrayRequests();
    }

    /**
     * Allow real HTTP requests for specific test groups
     */
    protected function allowRealRequests(): void
    {
        // Remove any existing fakes and allow real requests
        Http::spy(); // This clears existing fakes
        Http::preventStrayRequests(false);

        // Verify we have valid credentials for integration tests
        if (!env('POLAR_API_KEY') || !env('POLAR_ORGANIZATION_ID')) {
            $this->markTestSkipped('Integration tests require POLAR_API_KEY and POLAR_ORGANIZATION_ID environment variables');
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            CashierPolarServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // Load package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Create users table for testing
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Load the cashier-polar stub migration
        $migration = include __DIR__ . '/../database/migrations/create_cashier_polar_table.php.stub';
        $migration->up();

        // Create webhook_calls table for spatie/webhook-client
        $webhookMigration = include __DIR__ . '/../vendor/spatie/laravel-webhook-client/database/migrations/create_webhook_calls_table.php.stub';
        $webhookMigration->up();
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        // Use environment variables for integration tests if they exist
        if (env('POLAR_API_KEY') && env('POLAR_ORGANIZATION_ID')) {
            config()->set('cashier-polar.key', env('POLAR_API_KEY'));
            config()->set('cashier-polar.organization_id', env('POLAR_ORGANIZATION_ID'));

            // Set the base URL for sandbox environment
            config()->set('cashier-polar.base_url', 'https://sandbox-api.polar.sh/v1');
        } else {
            // Fallback to test values
            config()->set('cashier-polar.key', 'test-key');
            config()->set('cashier-polar.organization_id', 'test-org');
        }

        config()->set('cashier-polar.webhook_secret', 'test-secret');

        // Add webhook-client config
        config()->set('webhook-client.configs', [
            [
                'name' => 'polar',
                'signing_secret' => config('cashier-polar.webhook_secret'),
                'signature_header_name' => 'webhook-signature',
                'signature_validator' => \Mafrasil\CashierPolar\WebhookHandler\PolarSignatureValidator::class,
                'webhook_profile' => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
                'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
                'process_webhook_job' => \Mafrasil\CashierPolar\WebhookHandler\ProcessPolarWebhook::class,
            ],
        ]);
    }
}
