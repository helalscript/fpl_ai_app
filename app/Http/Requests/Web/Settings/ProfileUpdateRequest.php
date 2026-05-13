<?php

namespace App\Http\Requests\Web\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('fpl_team_id') && $this->input('fpl_team_id') === '') {
            $this->merge(['fpl_team_id' => null]);
        }
    }
}
