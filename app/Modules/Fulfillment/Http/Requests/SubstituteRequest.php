<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubstituteRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
