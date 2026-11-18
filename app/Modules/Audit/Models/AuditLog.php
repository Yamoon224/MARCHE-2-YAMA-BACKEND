<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Shared\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Entrée du journal d'audit : écriture seule, chaînée par hachage (chaque entrée porte le hachage de la précédente).
 *
 * @property int $id
 * @property int $user_id
 * @property string $method
 * @property string $path
 * @property int $status
 * @property string|null $ip
 * @property string $payload
 * @property Carbon $created_at
 * @property string|null $prev_hash
 * @property string $hash
 */
class AuditLog extends Model
{
    use Immutable;

    public $timestamps = false;

    protected $fillable = ['user_id', 'method', 'path', 'status', 'ip', 'payload', 'created_at', 'prev_hash', 'hash'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** Hachage de l'entrée à partir de celui de la précédente. */
    public static function hashOf(?string $previousHash, int $userId, string $method, string $path, int $status, ?string $ip, string $payload, string $createdAt): string
    {
        return hash('sha256', ($previousHash ?? '').json_encode(
            [$userId, $method, $path, $status, $ip, $payload, $createdAt],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }
}
