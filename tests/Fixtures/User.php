<?php

namespace Mafrasil\CashierPolar\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Model;
use Mafrasil\CashierPolar\Concerns\Billable;

class User extends Model
{
    use Billable, HasFactory;

    protected $guarded = [];

    public static function factory()
    {
        return UserFactory::new();
    }
}
