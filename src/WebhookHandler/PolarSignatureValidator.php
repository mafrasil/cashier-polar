<?php

namespace Mafrasil\CashierPolar\WebhookHandler;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PolarSignatureValidator
{
    public function isValid(Request $request): bool
    {
        $signingSecret = base64_encode(config('cashier-polar.webhook_secret'));

        if (! $request->header('webhook-id') ||
            ! $request->header('webhook-signature') ||
            ! $request->header('webhook-timestamp')) {
            return false;
        }

        try {
            $wh = new \StandardWebhooks\Webhook($signingSecret);

            return (bool) $wh->verify(
                $request->getContent(),
                [
                    'webhook-id' => $request->header('webhook-id'),
                    'webhook-signature' => $request->header('webhook-signature'),
                    'webhook-timestamp' => $request->header('webhook-timestamp'),
                ]
            );

        } catch (\Exception $e) {
            Log::error('Polar Webhook Validation Error', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            return false;
        }
    }
}
