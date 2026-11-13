<?php

declare(strict_types=1);

namespace App\Modules\Claims\Http\Requests;

use App\Modules\Claims\Enums\ClaimResolution;
use App\Modules\Claims\Enums\CostBearer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideClaimRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accept,reject'],
            // Décision toujours motivée, y compris un rejet.
            'note' => ['required', 'string', 'min:3', 'max:1000'],
            'resolution' => ['required_if:decision,accept', 'nullable', Rule::enum(ClaimResolution::class)],
            'cost_bearer' => ['required_if:decision,accept', 'nullable', Rule::enum(CostBearer::class)],
            'amount_minor' => ['required_if:resolution,refund', 'nullable', 'integer', 'min:1', 'max:100000000'],
        ];
    }
}
