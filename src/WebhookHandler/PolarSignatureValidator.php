<?php

namespace Mafrasil\CashierPolar\WebhookHandler;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PolarSignatureValidator
{
    public function isValid(Request $request): bool
    {
        if (! $request->header('webhook-id') ||
            ! $request->header('webhook-signature') ||
            ! $request->header('webhook-timestamp')) {
            return false;
        }

        try {
            $secret = config('cashier-polar.webhook_secret');
            $payload = $request->getContent();
            $timestamp = $request->header('webhook-timestamp');
            $webhookId = $request->header('webhook-id');

            $signatureMessage = $webhookId.'.'.$timestamp.'.'.$payload;
            $hmac = hash_hmac('sha256', $signatureMessage, $secret, true);
            $expectedSignature = 'v1,'.base64_encode($hmac);

            return hash_equals($expectedSignature, $request->header('webhook-signature'));

        } catch (\Exception $e) {
            Log::error('Polar Webhook Validation Error', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            return false;
        }
    }
}
