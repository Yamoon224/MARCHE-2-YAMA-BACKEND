<?php

declare(strict_types=1);

namespace App\Modules\Claims\Models;

use App\Modules\Claims\Enums\ClaimResolution;
use App\Modules\Claims\Enums\ClaimStatus;
use App\Modules\Claims\Enums\CostBearer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ClaimStatus $status
 * @property ClaimResolution|null $resolution
 * @property CostBearer|null $cost_bearer
 * @property Carbon|null $decided_at
 */
class Claim extends Model
{
    protected $fillable = [
        'order_id', 'user_id', 'status', 'description', 'resolution', 'cost_bearer', 'refund_amount_minor',
        'refund_completed', 'decided_by', 'decision_note', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'resolution' => ClaimResolution::class,
            'cost_bearer' => CostBearer::class,
            'refund_amount_minor' => 'integer',
            'refund_completed' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    /** @return HasMany<ClaimItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ClaimItem::class)->orderBy('id');
    }

    /** @return HasMany<ClaimPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ClaimPhoto::class)->orderBy('id');
    }

    /** Plafond remboursable de toute la réclamation : somme des plafonds de ses lignes. */
    public function claimableMinor(): int
    {
        return (int) $this->items->sum('claimable_minor');
    }
}
