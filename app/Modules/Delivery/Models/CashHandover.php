<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;

/** Remise des espèces d'un livreur à la caisse du magasin. Jamais modifiée une fois enregistrée. */
class CashHandover extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['courier_id', 'received_by', 'expected_minor', 'counted_minor', 'variance_minor', 'deliveries_count', 'note'];

    protected function casts(): array
    {
        return [
            'expected_minor' => 'integer', 'counted_minor' => 'integer', 'variance_minor' => 'integer',
            'deliveries_count' => 'integer', 'created_at' => 'datetime',
        ];
    }
}
