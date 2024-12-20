<?php

namespace Mafrasil\CashierPolar\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PolarCustomer extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
