<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['method' => [
            'required',
            Rule::enum(PaymentMethod::class)->only([PaymentMethod::MobileMoney, PaymentMethod::Card, PaymentMethod::CashOnDelivery]),
            // Seuls les moyens ouverts par la configuration sont acceptés (carte uniquement par défaut).
            Rule::in(config('payments.methods')),
        ]];
    }
}
