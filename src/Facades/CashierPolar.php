<?php

namespace Mafrasil\CashierPolar\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Mafrasil\CashierPolar\CashierPolar
 */
class CashierPolar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Mafrasil\CashierPolar\CashierPolar::class;
    }
}
