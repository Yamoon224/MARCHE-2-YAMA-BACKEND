<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 .-]{8,20}$/'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Indiquez un numéro de téléphone valide.'];
    }
}
