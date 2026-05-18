<?php

namespace App\Services\API\V1\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;

class OtpService
{
    public function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
        // return '1234'; // for testing purposes
    }

    public function setOtp(User $user, string $otp, int $ttlMinutes = 10): User
    {
        $user->forceFill([
            'otp' => $otp,
            'is_otp_verified' => false,
            'otp_expires_at' => CarbonImmutable::now()->addMinutes($ttlMinutes),
        ])->save();

        return $user;
    }

    public function verifyOtp(User $user, string $otp): bool
    {
        if ($user->otp === null || $user->otp_expires_at === null) {
            return false;
        }

        if (CarbonImmutable::now()->greaterThan($user->otp_expires_at)) {
            return false;
        }

        return hash_equals((string) $user->otp, (string) $otp);
    }

    public function clearOtp(User $user): User
    {
        $user->forceFill([
            'otp' => null,
            'otp_expires_at' => null,
        ])->save();

        return $user;
    }
}
