<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Helpers\Helper;
use App\Http\Controllers\API\Concerns\ApiResponses;
use App\Http\Requests\API\V1\Auth\UpdatePasswordRequest;
use App\Http\Requests\API\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\API\V1\UserResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController
{
    use ApiResponses;

    public function me(Request $request): JsonResponse
    {
        try {
            return self::jsonResponse(true, 'Authenticated user.', 200, [
                'user' => new UserResource($request->user()),
            ]);
        } catch (Exception $e) {
            Log::error('AuthController@me: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to get authenticated user.', 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $request->user()?->currentAccessToken();

            if ($token) {
                $token->delete();
            }

            return self::jsonResponse(true, 'Logged out successfully.', 200);
        } catch (Exception $e) {
            Log::error('AuthController@logout: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to logout.', 500);
        }
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();
            if ($request->hasFile('avatar')) {
                if (! empty($user->avatar)) {
                    Helper::fileDelete(public_path($user->getRawOriginal('avatar')));
                }
                $avatarPath = Helper::fileUpload($request->file('avatar'), 'user/avatar', Helper::getFileName($request->file('avatar')));
                $validated['avatar'] = $avatarPath;
            }

            $user->fill($validated);
            $user->save();

            return self::jsonResponse(true, 'Profile updated successfully.', 200, [
                'user' => new UserResource($user->refresh()),
            ]);
        } catch (Exception $e) {
            Log::error('AuthController@updateProfile: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to update profile.', 500);
        }
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->fill([
                'password' => $request->validated('new_password'),
            ]);
            $user->save();

            return self::jsonResponse(true, 'Password updated successfully.', 200, [
                'user' => new UserResource($user->refresh()),
            ]);
        } catch (Exception $e) {
            Log::error('AuthController@updatePassword: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to update password.', 500);
        }
    }

    public function updateFplTeamId(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fpl_team_id' => ['required', 'integer', 'min:1'],
            ]);
            $user = $request->user();
            $user->fpl_team_id = $validated['fpl_team_id'];
            $user->save();

            return self::jsonResponse(true, 'FPL team updated successfully.', 200, [
                'user' => new UserResource($user->refresh()),
            ]);
        } catch (Exception $e) {
            Log::error('AuthController@updateFplTeamId: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to update FPL team.', 500);
        }
    }
}
