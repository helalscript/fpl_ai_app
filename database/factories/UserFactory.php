<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'fpl_team_id' => null,
            'avatar' => null,
            'role' => 'user',
            'otp' => null,
            'is_otp_verified' => true,
            'otp_expires_at' => null,
            'reset_password_token' => null,
            'reset_password_token_expire_at' => null,
            'last_seen' => null,
            'remember_token' => Str::random(10),
            'status' => 'active',
            'is_agreed_terms' => true,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the user's email OTP is not verified yet (registration incomplete).
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_otp_verified' => false,
        ]);
    }

    /**
     * Email OTP verified (can use API login).
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_otp_verified' => true,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
