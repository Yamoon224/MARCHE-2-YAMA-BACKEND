<?php

declare(strict_types=1);

namespace App\Modules\Claims\Http\Requests;

use App\Modules\Claims\Enums\ClaimIssue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenClaimRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.order_line_id' => ['required', 'integer', 'min:1'],
            'items.*.issue' => ['required', Rule::enum(ClaimIssue::class)],
            // Quantité entière dans l'unité de base (pièces, g ou ml) ; plafonnée par ce qui a été reçu.
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            // Contrôle du contenu réel (pas seulement de l'extension) : image JPEG, PNG ou WebP uniquement.
            'photos' => ['nullable', 'array', 'max:'.(int) config('claims.max_photos')],
            'photos.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.(int) config('claims.max_photo_kilobytes')],
        ];
    }
}
