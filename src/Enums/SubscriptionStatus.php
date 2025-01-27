<?php

namespace Mafrasil\CashierPolar\Enums;

enum SubscriptionStatus: string {
    case INCOMPLETE = 'incomplete';
    case INCOMPLETE_EXPIRED = 'incomplete_expired';
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case UNPAID = 'unpaid';
    case REVOKED = 'revoked';

    public function isValid(): bool
    {
        return match ($this) {
            self::ACTIVE, self::TRIALING => true,
            default => false
        };
    }
}
