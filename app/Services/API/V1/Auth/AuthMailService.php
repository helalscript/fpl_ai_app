<?php

namespace App\Services\API\V1\Auth;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthMailService
{
    /**
     * Send OTP to the user's email.
     *
     * @param  'registration'|'password_reset'|'resend'  $purpose
     */
    public function sendOtpEmail(User $user, string $otp, string $purpose, int $expiresMinutes = 10): void
    {
        Log::info('Sending OTP to email: '.$user->email);
        Mail::to($user->email)->send(new OtpMail($user, $otp, $purpose, $expiresMinutes));
    }
}
