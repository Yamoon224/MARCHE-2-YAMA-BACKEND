<?php

declare(strict_types=1);

namespace App\Modules\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenSessionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['opening_float_minor' => ['required', 'integer', 'min:0', 'max:100000000']];
    }
}
