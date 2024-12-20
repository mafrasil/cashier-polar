<?php

return [
    'configs' => [
        [
            'name' => 'polar',
            'signing_secret' => config('cashier-polar.webhook_secret'),
            'signature_header_name' => 'webhook-signature',
            'signature_validator' => \Mafrasil\CashierPolar\WebhookHandler\PolarSignatureValidator::class,
            'webhook_profile' => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
            'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'process_webhook_job' => \Mafrasil\CashierPolar\WebhookHandler\ProcessPolarWebhook::class,
        ],
    ],
];
