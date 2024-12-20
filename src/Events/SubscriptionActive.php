<?php

namespace Mafrasil\CashierPolar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mafrasil\CashierPolar\Models\PolarSubscription;

class SubscriptionActive
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PolarSubscription $subscription,
        public array $payload
    ) {}
}
