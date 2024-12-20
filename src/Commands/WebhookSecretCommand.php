<?php

namespace Mafrasil\CashierPolar\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class WebhookSecretCommand extends Command
{
    public $signature = 'cashier-polar:webhook-secret';
    public $description = 'Generate a webhook secret for Polar';

    public function handle(): int
    {
        $secret = Str::random(32);

        $this->info('Webhook Secret: ' . $secret);
        $this->info('');
        $this->info('Add this to your .env file:');
        $this->info('POLAR_WEBHOOK_SECRET=' . $secret);

        return self::SUCCESS;
    }
}
