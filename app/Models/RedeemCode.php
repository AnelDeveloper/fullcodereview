<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RedeemCode extends Model
{
    protected $fillable = [
        'code',
        'email',
        'user_id',
        'lemon_order_id',
        'amount_cents',
        'selected_categories',
        'used_at',
        'expires_at',
        'github_access_token',
        'github_login',
        'github_avatar_url',
    ];

    protected $hidden = ['github_access_token'];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
        'selected_categories' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function analysis()
    {
        return $this->hasOne(Analysis::class);
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

    public function isValid(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }
}
