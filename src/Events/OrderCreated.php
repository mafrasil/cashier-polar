<?php

namespace Mafrasil\CashierPolar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mafrasil\CashierPolar\Models\PolarTransaction;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PolarTransaction $transaction,
        public array $payload
    ) {}
}
