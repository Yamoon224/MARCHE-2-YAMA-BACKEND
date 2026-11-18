<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'subtotal' => ['required', 'integer', 'min:0'],
        ];
    }
}
