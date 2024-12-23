<?php

namespace Mafrasil\CashierPolar\Enums;

enum SubscriptionStatus: string
{
    case INCOMPLETE = 'incomplete';
    case ACTIVE = 'active';
    case CANCELED = 'canceled';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';

    public function isValid(): bool
    {
        return match ($this) {
            self::ACTIVE => true,
            default => false
        };
    }
}
