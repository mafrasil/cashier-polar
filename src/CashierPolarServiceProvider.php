<?php

namespace Mafrasil\CashierPolar;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mafrasil\CashierPolar\Commands\CashierPolarCommand;
use Mafrasil\CashierPolar\Commands\WebhookSecretCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookProcessor;

class CashierPolarServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('cashier-polar')
            ->hasConfigFile()
            ->hasMigration('create_cashier_polar_table')
            ->hasCommands([
                CashierPolarCommand::class,
                WebhookSecretCommand::class,
            ]);
    }

    public function packageRegistered()
    {
        $this->app->singleton(CashierPolar::class);
    }

    public function packageBooted()
    {
        $this->registerRoutes();
    }

    protected function registerRoutes()
    {
        Route::post('polar/webhook', function (Request $request) {
            return (new WebhookProcessor($request, new WebhookConfig(config('webhook-client.configs.0'))))->process();
        })->name('cashier-polar.webhook');
    }
}
