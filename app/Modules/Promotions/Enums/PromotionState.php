<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Enums;

enum PromotionState: string
{
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Disabled = 'disabled';

    public function reason(): string
    {
        return match ($this) {
            self::Active => '',
            self::Scheduled => "Ce code promotionnel n'est pas encore valable.",
            self::Expired => 'Ce code promotionnel a expiré.',
            self::Exhausted => "Ce code promotionnel a atteint son nombre maximal d'utilisations.",
            self::Disabled => "Ce code promotionnel n'est pas actif.",
        };
    }
}
