<?php

declare(strict_types=1);

namespace App\Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 */
class PosSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cashier_id', 'opening_float_minor', 'expected_cash_minor', 'counted_minor', 'variance_minor', 'note',
        'open_key', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'cashier_id' => 'integer', 'opening_float_minor' => 'integer', 'expected_cash_minor' => 'integer',
            'counted_minor' => 'integer', 'variance_minor' => 'integer', 'opened_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    /** @return HasMany<PosSale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
