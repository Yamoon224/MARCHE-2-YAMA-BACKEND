<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Donne au modèle un UUID public (colonne `uuid`), généré à la création et utilisé dans les URLs
 * (liaison de route) à la place de la clé entière interne.
 *
 * @mixin Model
 */
trait HasPublicUuid
{
    public static function bootHasPublicUuid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
