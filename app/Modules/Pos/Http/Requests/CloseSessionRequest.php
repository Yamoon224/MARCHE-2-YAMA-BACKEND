<?php

declare(strict_types=1);

namespace App\Modules\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseSessionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'counted_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
