<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\SaleUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sku' => ['required', 'string', 'max:40', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'sale_unit' => ['required', Rule::enum(SaleUnit::class)],
            'is_weight_variable' => ['boolean'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'compare_at_price_minor' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['boolean'],
            'vat_rate_bp' => ['integer', 'min:0', 'max:10000'],
            'perishable' => ['boolean'],
            'track_stock' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
