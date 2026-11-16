<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupOrderRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }
}
