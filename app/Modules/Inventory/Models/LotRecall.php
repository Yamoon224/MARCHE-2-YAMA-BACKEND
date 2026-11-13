<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class LotRecall extends Model
{
    protected $fillable = ['stock_lot_id', 'reason', 'recalled_by'];
}
