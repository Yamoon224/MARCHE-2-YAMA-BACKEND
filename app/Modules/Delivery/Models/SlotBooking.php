<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;

class SlotBooking extends Model
{
    protected $fillable = ['delivery_slot_id', 'order_id'];
}
