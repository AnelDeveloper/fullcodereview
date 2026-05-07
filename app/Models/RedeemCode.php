<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One row = one **category slot** (e.g. "1 Security review available").
 *
 * Buying Security + Database creates two rows; running a review with both
 * marks one of each as used. A user's "credits" are the count of unused
 * rows grouped by category.
 *
 * The legacy table name `redeem_codes` is kept; semantically these are
 * per-category slots now. `code` and `email` are nullable holdovers from
 * the prior emailed-redeem-code flow (no longer surfaced to users).
 */
class RedeemCode extends Model
{
    protected $fillable = [
        'code',
        'email',
        'user_id',
        'lemon_order_id',
        'amount_cents',
        'category',
        'used_at',
        'used_by_analysis_id',
        'expires_at',
        'github_access_token',
        'github_login',
        'github_avatar_url',
    ];

    protected $hidden = ['github_access_token'];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function analysis()
    {
        return $this->belongsTo(Analysis::class, 'used_by_analysis_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->whereNull('used_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public static function generate(): string
    {
        $segments = collect(range(1, 3))
            ->map(fn () => strtoupper(Str::random(4)))
            ->all();

        return 'VIBE-' . implode('-', $segments);
    }

    public function isUsed(): bool
    {
        return ! is_null($this->used_at);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
