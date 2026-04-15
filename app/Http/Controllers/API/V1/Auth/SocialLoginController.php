<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\Concerns\ApiResponses;
use App\Http\Requests\API\V1\Auth\SocialLoginRequest;
use App\Http\Resources\API\V1\UserResource;
use App\Services\API\V1\Auth\SocialLoginService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SocialLoginController
{
    use ApiResponses;

    public function __construct(public SocialLoginService $socialLoginService)
    {
        //
    }

    public function SocialLogin(SocialLoginRequest $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|in:google',
            'token' => 'required',
        ]);
        try {
            $user = $this->socialLoginService->login($validated);

            $token = $user->createToken('auth')->plainTextToken;

            return self::jsonResponse(true, 'Social login successful.', 200, [
                'user' => new UserResource($user),
                'token' => $token,
            ]);
        } catch (Exception $e) {
            Log::error('SocialLoginController@SocialLogin: '.$e->getMessage());

            return self::jsonErrorResponse('Social login failed.', 500);
        }
    }
}
