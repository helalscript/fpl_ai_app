<?php

namespace App\Services\API\V1\Auth;

use Illuminate\Support\Facades\Password;

class PasswordResetService
{
    /**
     * @param  array{email:string}  $payload
     */
    public function sendResetLink(array $payload): string
    {
        return Password::sendResetLink(['email' => $payload['email']]);
    }

    /**
     * @param  array{token:string,email:string,password:string,password_confirmation:string}  $payload
     */
    public function reset(array $payload): string
    {
        return Password::reset(
            [
                'email' => $payload['email'],
                'token' => $payload['token'],
                'password' => $payload['password'],
                'password_confirmation' => $payload['password_confirmation'],
            ],
            function ($user) use ($payload): void {
                $user->forceFill([
                    'password' => $payload['password'],
                ])->save();
            }
        );
    }
}
