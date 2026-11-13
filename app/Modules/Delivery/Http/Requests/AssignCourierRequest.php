<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignCourierRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['courier_id' => ['required', 'integer', 'min:1']];
    }
}
