<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            // Quantité entière dans l'unité de base : pièces, grammes ou millilitres.
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            // Consigne de substitution : accepter un remplaçant du préparateur, ou le refuser.
            'items.*.substitution' => ['nullable', 'in:allow,refuse'],

            'fulfillment' => ['nullable', 'in:pickup,delivery'],
            'delivery' => ['required_if:fulfillment,delivery', 'array'],
            'delivery.zone_id' => ['required_with:delivery', 'integer', 'min:1'],
            'delivery.slot_id' => ['required_with:delivery', 'integer', 'min:1'],
            'delivery.phone' => ['required_with:delivery', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            // Adresse africaine : repères et géolocalisation plutôt qu'une adresse postale stricte.
            'delivery.address' => ['required_with:delivery', 'array'],
            'delivery.address.neighborhood' => ['required_with:delivery', 'string', 'max:120'],
            'delivery.address.landmark' => ['nullable', 'string', 'max:255'],
            'delivery.address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery.address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
