<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Models;

use App\Modules\Fulfillment\Database\Factories\PickListFactory;
use App\Modules\Fulfillment\Enums\PickListStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property PickListStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class PickList extends Model
{
    /** @use HasFactory<PickListFactory> */
    use HasFactory;

    protected $fillable = ['order_id', 'status', 'picker_id', 'started_at', 'completed_at'];

    protected static function newFactory(): PickListFactory
    {
        return PickListFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => PickListStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return HasMany<PickLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PickLine::class)->orderBy('id');
    }
}
