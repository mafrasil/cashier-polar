<?php

namespace Mafrasil\CashierPolar\WebhookHandler;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class PolarSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signingSecret = $config->signingSecret;
        $wh = new \StandardWebhooks\Webhook($signingSecret);

        return boolval($wh->verify(
            $request->getContent(),
            [
                'webhook-id' => $request->header('webhook-id'),
                'webhook-signature' => $request->header('webhook-signature'),
                'webhook-timestamp' => $request->header('webhook-timestamp'),
            ]
        ));
    }
}
