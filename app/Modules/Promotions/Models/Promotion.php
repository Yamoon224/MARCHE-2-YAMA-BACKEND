<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Models;

use App\Modules\Promotions\Database\Factories\PromotionFactory;
use App\Modules\Promotions\Enums\PromotionState;
use App\Modules\Promotions\Enums\PromotionType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property PromotionType $type
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    protected $fillable = ['code', 'description', 'type', 'value', 'min_order_minor', 'max_uses', 'starts_at', 'ends_at', 'is_active'];

    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'integer',
            'min_order_minor' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function state(?CarbonInterface $now = null): PromotionState
    {
        $now ??= now();

        return match (true) {
            ! $this->is_active => PromotionState::Disabled,
            $this->max_uses !== null && $this->used_count >= $this->max_uses => PromotionState::Exhausted,
            $this->starts_at !== null && $this->starts_at->isAfter($now) => PromotionState::Scheduled,
            $this->ends_at !== null && $this->ends_at->isBefore($now) => PromotionState::Expired,
            default => PromotionState::Active,
        };
    }

    /** Remise en centimes pour ce sous-total, jamais supérieure au sous-total. */
    public function discountFor(int $subtotalMinor): int
    {
        $discount = match ($this->type) {
            PromotionType::Percent => intdiv($subtotalMinor * min($this->value, 100), 100),
            PromotionType::Fixed => $this->value,
        };

        return min($discount, $subtotalMinor);
    }
}
