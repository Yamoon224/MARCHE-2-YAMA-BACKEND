<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = ['provider', 'event_id', 'outcome', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
