<?php

namespace Mafrasil\CashierPolar\WebhookHandler;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PolarSignatureValidator
{
    public function isValid(Request $request): bool
    {
        // Base64 encode the signing secret as per Polar's documentation
        $signingSecret = base64_encode(config('cashier-polar.webhook_secret'));

        // Ensure we have all required headers
        if (! $request->header('webhook-id') ||
            ! $request->header('webhook-signature') ||
            ! $request->header('webhook-timestamp')) {
            Log::error('Missing required webhook headers');

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
