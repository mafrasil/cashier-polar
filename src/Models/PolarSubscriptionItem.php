<?php

namespace Mafrasil\CashierPolar\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolarSubscriptionItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PolarSubscription::class);
    }
}
