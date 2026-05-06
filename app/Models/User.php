<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'email_verified_at',
        'github_access_token',
        'github_login',
        'github_avatar_url',
    ];

    protected $hidden = ['password', 'remember_token', 'api_token', 'github_access_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function analyses()
    {
        return $this->hasMany(Analysis::class);
    }

    public function redeemCodes()
    {
        return $this->hasMany(RedeemCode::class);
    }

    public static function generateApiToken(): string
    {
        return Str::random(60);
    }
}
