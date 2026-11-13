<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un envoi SMS ou e-mail d'une notification. Le destinataire est chiffré au repos. */
class NotificationDelivery extends Model
{
    protected $fillable = ['user_notification_id', 'medium', 'recipient', 'status', 'attempts', 'last_error', 'sent_at'];

    protected $hidden = ['recipient'];

    protected function casts(): array
    {
        return ['recipient' => 'encrypted', 'attempts' => 'integer', 'sent_at' => 'datetime'];
    }

    /** @return BelongsTo<UserNotification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(UserNotification::class, 'user_notification_id');
    }
}
