<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['stock_lot_id', 'type', 'quantity', 'reason', 'user_id'];
}
