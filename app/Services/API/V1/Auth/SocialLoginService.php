<?php

namespace App\Services\API\V1\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginService
{
    /**
     * Minimal social login: create or update user by email.
     *
     * @param  array{provider:string,email:string,name:string,avatar?:string|null,fpl_team_id?:int|null}  $payload
     */
    public function login(array $payload): User
    {
        $socialUser = Socialite::driver($payload['provider'])
            ->stateless()
            ->userFromToken($payload['token']);

        return DB::transaction(function () use ($socialUser): User {
            $user = User::query()->withTrashed()->where('email', $socialUser->getEmail())->first();

            if ($user && $user->trashed()) {
                $user->restore();
            }

            if (! $user) {
                $user = User::query()->create([
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'password' => Str::password(32),
                    'fpl_team_id' => null,
                    'avatar' => $socialUser->getAvatar() ?? null,
                    'status' => 'active',
                    'role' => 'user',
                    'is_agreed_terms' => true,
                    'is_otp_verified' => true,
                ]);
            } else {
                $user->forceFill([
                    'name' => $socialUser->getName(),
                    'avatar' => $socialUser->getAvatar() ?? $user->avatar,
                    'fpl_team_id' => null,
                    'last_seen' => now(),
                ])->save();
            }

            return $user->refresh();
        });
    }
}
