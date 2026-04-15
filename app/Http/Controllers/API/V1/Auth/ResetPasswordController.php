<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\Concerns\ApiResponses;
use App\Http\Requests\API\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\API\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\API\V1\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\API\V1\Auth\AuthMailService;
use App\Services\API\V1\Auth\OtpService;
use App\Services\API\V1\Auth\PasswordResetService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class ResetPasswordController
{
    use ApiResponses;

    public function __construct(public PasswordResetService $passwordResetService, public OtpService $otpService, public AuthMailService $authMailService)
    {
        //
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = $this->passwordResetService->sendResetLink($request->validated());

            if ($status !== Password::RESET_LINK_SENT) {
                return self::jsonErrorResponse(__($status), 422);
            }

            /** @var User $user */
            $user = User::query()->where('email', $request->validated('email'))->firstOrFail();
            $otp = $this->otpService->generateOtp();
            $this->otpService->setOtp($user, $otp);
            $this->authMailService->sendOtpEmail($user->refresh(), $otp, 'password_reset');

            return self::jsonResponse(true, 'Password reset link sent.', 200);
        } catch (Exception $e) {
            Log::error('ResetPasswordController@forgotPassword: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to send password reset link.', 500);
        }
    }

    public function VerifyOTP(VerifyOtpRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = User::query()->where('email', $request->validated('email'))->firstOrFail();

            if (! $this->otpService->verifyOtp($user, $request->validated('otp'))) {
                return self::jsonErrorResponse('Invalid or expired OTP.', 422);
            }

            $this->otpService->clearOtp($user);

            $plainResetToken = Password::broker()->createToken($user->refresh());

            return self::jsonResponse(true, 'OTP verified successfully. Use this token with your email to set a new password.', 200, [
                'email' => $user->email,
                'token' => $plainResetToken,
            ]);
        } catch (Exception $e) {
            Log::error('ResetPasswordController@VerifyOTP: '.$e->getMessage());

            return self::jsonErrorResponse('OTP verification failed.', 500);
        }
    }

    public function ResetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();

            $status = $this->passwordResetService->reset([
                'token' => $payload['token'],
                'email' => $payload['email'],
                'password' => $payload['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ]);

            if ($status !== Password::PASSWORD_RESET) {
                return self::jsonErrorResponse(__($status), 422);
            }

            return self::jsonResponse(true, 'Password reset successful.', 200);
        } catch (Exception $e) {
            Log::error('ResetPasswordController@ResetPassword: '.$e->getMessage());

            return self::jsonErrorResponse('Password reset failed.', 500);
        }
    }
}
