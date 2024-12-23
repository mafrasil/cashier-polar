<?php

namespace Mafrasil\CashierPolar;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mafrasil\CashierPolar\Commands\CashierPolarCommand;
use Mafrasil\CashierPolar\Commands\WebhookSecretCommand;
use Mafrasil\CashierPolar\WebhookHandler\PolarSignatureValidator;
use Mafrasil\CashierPolar\WebhookHandler\ProcessPolarWebhook;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CashierPolarServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('cashier-polar')
            ->hasConfigFile(['cashier-polar'])
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
        Route::post(config('cashier-polar.path', 'webhooks/polar'), function (Request $request) {
            // Log the raw incoming webhook
            Log::info('Incoming Polar webhook', [
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
            ]);

            try {
                $validator = new PolarSignatureValidator;

                if (! $validator->isValid($request)) {
                    Log::error('Polar webhook signature validation failed', [
                        'webhook_id' => $request->header('webhook-id'),
                        'webhook_timestamp' => $request->header('webhook-timestamp'),
                        // Don't log the full signature for security
                        'has_signature' => ! empty($request->header('webhook-signature')),
                        'content_length' => strlen($request->getContent()),
                    ]);

                    return response()->json(['message' => 'Invalid signature'], 400);
                }

                $payload = json_decode($request->getContent(), true);
                ProcessPolarWebhook::dispatchSync($payload);

                return response()->json(['message' => 'Webhook processed']);

            } catch (\Exception $e) {
                Log::error('Polar webhook processing error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }
        })->name('cashier-polar.webhook');
    }
}
