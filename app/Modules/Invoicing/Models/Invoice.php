<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Models;

use App\Shared\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Facture validée : non modifiable, toute correction passe par un avoir. */
class Invoice extends Model
{
    use Immutable;

    protected $fillable = [
        'number', 'order_id', 'user_id', 'issued_at', 'currency', 'delivery_fee_minor',
        'discount_minor', 'total_minor', 'vat_minor', 'net_minor',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'delivery_fee_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
            'vat_minor' => 'integer',
            'net_minor' => 'integer',
        ];
    }

    /** @return HasMany<CreditNote, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('id');
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
