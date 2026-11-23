<?php

declare(strict_types=1);

namespace App\Shared;

use Illuminate\Support\Facades\DB;

/**
 * Identifiants publics (UUID) : l'API n'expose jamais la clé interne d'une table, qui reste un entier
 * (jointures rapides, clés étrangères inchangées). Ce service traduit dans les deux sens sans que les
 * modules aient à connaître les modèles des autres : il lit seulement la colonne `uuid` de la table.
 */
final class PublicId
{
    /** @var array<string, array<int, string|null>> */
    private static array $uuids = [];

    /** Clé interne d'une ligne à partir de son UUID, ou `null` si le format ou la ligne n'existe pas. */
    public static function resolve(string $table, string $uuid): ?int
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) !== 1) {
            return null;
        }

        $id = DB::table($table)->where('uuid', strtolower($uuid))->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Comme `resolve`, mais répond 404 si l'identifiant est inconnu. */
    public static function resolveOrFail(string $table, string $uuid, string $message = 'Introuvable.'): int
    {
        return self::resolve($table, $uuid) ?? abort(404, $message);
    }

    /** UUID public d'une ligne à partir de sa clé interne (mémorisé le temps de la requête). */
    public static function uuid(string $table, ?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return self::$uuids[$table][$id] ??= DB::table($table)->where('id', $id)->value('uuid');
    }

    public static function flush(): void
    {
        self::$uuids = [];
    }
}
