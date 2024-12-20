<?php

namespace Mafrasil\CashierPolar\Tests\Fixtures;

use Orchestra\Testbench\Factories\UserFactory as TestbenchUserFactory;

class UserFactory extends TestbenchUserFactory
{
    protected $model = User::class;
}
