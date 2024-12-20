<?php

namespace Mafrasil\CashierPolar\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mafrasil\CashierPolar\WebhookHandler\PolarSignatureValidator;
use Mafrasil\CashierPolar\WebhookHandler\ProcessPolarWebhook;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;

class WebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $webhookConfig = new WebhookConfig([
            'name' => 'polar',
            'signing_secret' => config('cashier-polar.webhook_secret'),
            'signature_validator' => PolarSignatureValidator::class,
            'webhook_profile' => ProcessEverythingWebhookProfile::class,
            'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'process_webhook_job' => ProcessPolarWebhook::class,
        ]);

        try {
            $webhookCall = $webhookConfig->webhookModel::create([
                'name' => $webhookConfig->name,
                'payload' => $request->input(),
            ]);

            dispatch(new ProcessPolarWebhook($webhookCall));

            return response()->json(['message' => 'ok']);
        } catch (\Exception $e) {
            logger()->error('Webhook processing failed: ' . $e->getMessage());
            return response()->json(['message' => 'Webhook processing failed'], 400);
        }
    }
}
