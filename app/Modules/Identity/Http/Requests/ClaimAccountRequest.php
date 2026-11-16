<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimAccountRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'min:8', 'max:255']];
    }
}
