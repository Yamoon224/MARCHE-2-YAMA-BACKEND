<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use App\Modules\Delivery\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property DeliveryStatus $status
 * @property string $confirmation_code
 * @property Carbon|null $assigned_at
 * @property Carbon|null $delivered_at
 */
class Delivery extends Model
{
    /** Au-delà, le code est bloqué : un responsable doit débloquer (protection contre la devinette). */
    public const MAX_CODE_ATTEMPTS = 5;

    protected $fillable = [
        'order_id', 'courier_id', 'status', 'confirmation_code', 'code_attempts',
        'cash_collected_minor', 'cash_handover_id', 'failure_reason', 'assigned_at', 'delivered_at',
    ];

    protected $hidden = ['confirmation_code'];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'confirmation_code' => 'encrypted',
            'code_attempts' => 'integer',
            'cash_collected_minor' => 'integer',
            'assigned_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return HasOne<DeliveryLocation, $this> */
    public function location(): HasOne
    {
        return $this->hasOne(DeliveryLocation::class);
    }

    public function isCodeLocked(): bool
    {
        return $this->code_attempts >= self::MAX_CODE_ATTEMPTS;
    }
}
