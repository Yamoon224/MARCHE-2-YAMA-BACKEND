<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Période d'un rapport : par défaut les 30 derniers jours, 366 jours au plus. */
class PeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group' => ['nullable', 'in:day,product,category'],
            'format' => ['nullable', 'in:json,csv'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'La date de fin doit suivre la date de début.',
            'from.date_format' => 'La date de début doit être au format AAAA-MM-JJ.',
            'to.date_format' => 'La date de fin doit être au format AAAA-MM-JJ.',
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->isEmpty() && $this->from()->diffInDays($this->to()) > 366) {
                $validator->errors()->add('from', 'La période ne peut pas dépasser 366 jours.');
            }
        }];
    }

    public function from(): CarbonImmutable
    {
        return $this->filled('from')
            ? CarbonImmutable::parse($this->string('from')->toString())->startOfDay()
            : CarbonImmutable::now()->subDays(29)->startOfDay();
    }

    public function to(): CarbonImmutable
    {
        return $this->filled('to')
            ? CarbonImmutable::parse($this->string('to')->toString())->endOfDay()
            : CarbonImmutable::now()->endOfDay();
    }
}
