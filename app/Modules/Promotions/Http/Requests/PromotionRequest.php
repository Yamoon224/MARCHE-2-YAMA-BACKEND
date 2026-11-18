<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Requests;

use App\Modules\Promotions\Enums\PromotionType;
use App\Modules\Promotions\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        /** @var Promotion|null $promotion */
        $promotion = $this->route('promotion');
        $req = $creating ? 'required' : 'sometimes';

        return [
            'code' => [$req, 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('promotions', 'code')->ignore($promotion?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => [$req, Rule::enum(PromotionType::class)],
            'value' => [$req, 'integer', 'min:1'],
            'min_order_minor' => ['sometimes', 'integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Promotion|null $promotion */
            $promotion = $this->route('promotion');
            $type = $this->input('type', $promotion?->type->value);
            $value = $this->input('value', $promotion?->value);

            if ($type === PromotionType::Percent->value && is_numeric($value) && (int) $value > 100) {
                $validator->errors()->add('value', 'Un pourcentage ne peut pas dépasser 100.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code existe déjà.',
            'code.regex' => 'Le code ne contient que des lettres majuscules, chiffres, tirets et underscores.',
            'ends_at.after' => 'La date de fin doit suivre la date de début.',
        ];
    }
}
