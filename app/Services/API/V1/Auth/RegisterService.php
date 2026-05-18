<?php

namespace App\Services\API\V1\Auth;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegisterService
{
    public function __construct(
        public OtpService $otpService,
        public AuthMailService $authMailService,
    ) {}

    /**
     * @param  array{name:string,email:string,password:string,fpl_team_id?:int|null}  $payload
     */
    public function register(array $payload): User
    {
        try {
            $otp = $this->otpService->generateOtp();

            $user = DB::transaction(function () use ($payload, $otp): User {
                $user = User::query()->create([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'password' => $payload['password'],
                    'fpl_team_id' => isset($payload['fpl_team_id']) ? $payload['fpl_team_id'] : null,
                    'status' => 'active',
                    'role' => 'user',
                    'is_agreed_terms' => true,
                    'is_otp_verified' => false,
                ]);

                $this->otpService->setOtp($user, $otp);

                return $user->refresh();

            });
            $this->authMailService->sendOtpEmail($user, $otp, 'registration');

            // $this->authMailService->sendOtpEmail($user, $otp, 'registration');

            return $user;
        } catch (Exception $e) {
            Log::error('RegisterService@register: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate a new OTP, store it, and email it (verification / resend flow).
     */
    public function resendVerificationOtp(User $user): void
    {
        if ($user->is_otp_verified) {
            return;
        }

        $otp = $this->otpService->generateOtp();
        $this->otpService->setOtp($user, $otp);
        $this->authMailService->sendOtpEmail($user->refresh(), $otp, 'resend');
    }
}
