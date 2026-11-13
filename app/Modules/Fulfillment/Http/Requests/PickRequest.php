<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PickRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Entier dans l'unité de base : pièces, grammes ou millilitres. 0 = rupture.
        return ['quantity' => ['required', 'integer', 'min:0', 'max:1000000']];
    }
}
