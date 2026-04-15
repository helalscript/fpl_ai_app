<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\Concerns\ApiResponses;
use App\Http\Requests\API\V1\Auth\LoginRequest;
use App\Http\Resources\API\V1\UserResource;
use App\Services\API\V1\Auth\LoginService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LoginController
{
    use ApiResponses;

    public function __construct(
        public LoginService $loginService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = $this->loginService->attempt($request->validated());

            if (! $user) {
                return self::jsonErrorResponse('Invalid credentials.', 401);
            }

            if (! $user->is_otp_verified) {
                return self::jsonErrorResponse(
                    'Please verify your email with the OTP we sent you before logging in.',
                    403,
                );
            }

            $user->forceFill(['last_seen' => now()])->save();

            $token = $user->createToken('auth')->plainTextToken;

            return self::jsonResponse(true, 'Login successful.', 200, [
                'user' => new UserResource($user),
                'token' => $token,
            ]);
        } catch (Exception $e) {
            Log::error('LoginController@login: '.$e->getMessage());

            return self::jsonErrorResponse('Login failed.', 500);
        }
    }
}
