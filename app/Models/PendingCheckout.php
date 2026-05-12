<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingCheckout extends Model
{
    protected $fillable = [
        'user_id',
        'stripe_session_id',
        'category_keys',
        'usd_total_cents',
        'discount_pct',
        'status',
        'matched_order_id',
        'matched_at',
    ];

    protected $casts = [
        'category_keys'   => 'array',
        'usd_total_cents' => 'integer',
        'discount_pct'    => 'integer',
        'matched_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
