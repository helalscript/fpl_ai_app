<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'fpl_team_id',
    'avatar',
    'role',
    'otp',
    'is_otp_verified',
    'otp_expires_at',
    'reset_password_token',
    'reset_password_token_expire_at',
    'last_seen',
    'status',
    'is_agreed_terms',
])]
#[Hidden([
    'password',
    'otp',
    'reset_password_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'fpl_team_id' => 'integer',
            'is_otp_verified' => 'boolean',
            'otp_expires_at' => 'datetime',
            'reset_password_token_expire_at' => 'datetime',
            'last_seen' => 'datetime',
            'is_agreed_terms' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the user's avatar.
     *
     * @param  string|null  $value
     */
    public function getAvatarAttribute($value): ?string
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        // Check if the request is an API request
        if (request()->is('api/*') && ! empty($value)) {
            return url($value);
        }

        // Return only the path for web requests
        return $value;
    }
}
