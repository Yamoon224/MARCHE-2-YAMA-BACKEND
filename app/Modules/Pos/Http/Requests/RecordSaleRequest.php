<?php

declare(strict_types=1);

namespace App\Modules\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordSaleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'tender' => ['required', 'in:cash,mobile_money,card'],
            // Espèces remises par le client : sert à rendre la monnaie (les autres moyens règlent le montant exact).
            'tendered_minor' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ];
    }
}
