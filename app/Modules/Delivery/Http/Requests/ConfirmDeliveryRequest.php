<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmDeliveryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            // Espèces réellement encaissées en F CFA (0 si la commande était déjà payée).
            'cash_collected_minor' => ['required', 'integer', 'min:0', 'max:100000000'],
        ];
    }
}
