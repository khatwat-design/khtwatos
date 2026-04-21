<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if (!$user) {
            return;
        }

        $this->merge([
            'availability_days' => $this->input('availability_days', $user->availability_days ?: [0, 1, 2, 3, 4]),
            'availability_start_time' => $this->input('availability_start_time', $user->availability_start_time ? substr($user->availability_start_time, 0, 5) : '09:00'),
            'availability_end_time' => $this->input('availability_end_time', $user->availability_end_time ? substr($user->availability_end_time, 0, 5) : '17:00'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'is_bookable' => ['sometimes', 'boolean'],
            'availability_days' => ['required', 'array', 'min:1'],
            'availability_days.*' => ['integer', 'between:0,6'],
            'availability_start_time' => ['required', 'date_format:H:i'],
            'availability_end_time' => ['required', 'date_format:H:i', 'after:availability_start_time'],
        ];
    }
}
