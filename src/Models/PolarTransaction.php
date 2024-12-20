<?php

namespace Mafrasil\CashierPolar\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PolarTransaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'total' => 'decimal:2',
        'tax' => 'decimal:2',
        'billed_at' => 'datetime',
    ];

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
