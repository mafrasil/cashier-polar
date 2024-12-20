<?php

namespace Mafrasil\CashierPolar\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mafrasil\CashierPolar\CashierPolarServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Mafrasil\\CashierPolar\\Tests\\Fixtures\\'.class_basename($modelName).'Factory'
        );
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
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Create users table for testing
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Load the cashier-polar stub migration
        $migration = include __DIR__.'/../database/migrations/create_cashier_polar_table.php.stub';
        $migration->up();

        // Create webhook_calls table for spatie/webhook-client
        $webhookMigration = include __DIR__.'/../vendor/spatie/laravel-webhook-client/database/migrations/create_webhook_calls_table.php.stub';
        $webhookMigration->up();
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('cashier-polar.key', 'test-key');
        config()->set('cashier-polar.organization_id', 'test-org');
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
