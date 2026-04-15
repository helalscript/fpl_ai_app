<?php

namespace App\Http\Requests\API\V1\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'file:image,max:5120'],
            'fpl_team_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
