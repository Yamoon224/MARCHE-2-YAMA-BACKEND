<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreZoneRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'fee_minor' => ['required', 'integer', 'min:0'],
            'free_above_minor' => ['nullable', 'integer', 'min:0'],
            'min_order_minor' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
