<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\Concerns\ApiResponses;
use App\Http\Requests\API\V1\Auth\RegisterRequest;
use App\Http\Requests\API\V1\Auth\ResendOtpRequest;
use App\Http\Requests\API\V1\Auth\VerifyEmailRequest;
use App\Http\Resources\API\V1\UserResource;
use App\Models\User;
use App\Services\API\V1\Auth\OtpService;
use App\Services\API\V1\Auth\RegisterService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RegisterController
{
    use ApiResponses;

    public function __construct(
        public RegisterService $registerService,
        public OtpService $otpService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->registerService->register($request->validated());

            return self::jsonResponse(
                true,
                'We have sent an OTP to your email for verification. Please verify your email to complete registration.',
                201,
                [
                    'user' => new UserResource($user->refresh()),
                ],
            );
        } catch (Exception $e) {
            Log::error('RegisterController@register: '.$e->getMessage());

            return self::jsonErrorResponse('Registration failed.', 500);
        }
    }

    public function VerifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = User::query()->where('email', $request->validated('email'))->firstOrFail();

            if (! $this->otpService->verifyOtp($user, $request->validated('otp'))) {
                return self::jsonErrorResponse('Invalid or expired OTP.', 422);
            }

            if ($user->is_otp_verified) {
                return self::jsonErrorResponse('Email is already verified.', 422);
            }

            $user->forceFill([
                'is_otp_verified' => true,
            ])->save();

            $this->otpService->clearOtp($user);

            $user = $user->refresh();
            $token = $user->createToken('auth')->plainTextToken;

            return self::jsonResponse(true, 'Registration completed. You are now logged in.', 200, [
                'user' => new UserResource($user),
                'token' => $token,
            ]);
        } catch (Exception $e) {
            Log::error('RegisterController@VerifyEmail: '.$e->getMessage());

            return self::jsonErrorResponse('Email verification failed.', 500);
        }
    }

    public function ResendOtp(ResendOtpRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = User::query()->where('email', $request->validated('email'))->firstOrFail();

            if ($user->is_otp_verified) {
                return self::jsonErrorResponse('Email is already verified.', 422);
            }

            $this->registerService->resendVerificationOtp($user);

            return self::jsonResponse(true, 'We have resent the OTP to your email.', 200);
        } catch (Exception $e) {
            Log::error('RegisterController@ResendOtp: '.$e->getMessage());

            return self::jsonErrorResponse('OTP resend failed.', 500);
        }
    }
}
