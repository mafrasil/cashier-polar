<?php

namespace Mafrasil\CashierPolar\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'metadata' => 'array',
    ];

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
}
