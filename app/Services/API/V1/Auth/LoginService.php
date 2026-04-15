<?php

namespace App\Services\API\V1\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    /**
     * @param  array{email:string,password:string}  $payload
     */
    public function attempt(array $payload): ?User
    {
        $user = User::query()->where('email', $payload['email'])->first();

        if (! $user) {
            return null;
        }

        if (! Hash::check($payload['password'], $user->password)) {
            return null;
        }

        return $user;
    }
}
