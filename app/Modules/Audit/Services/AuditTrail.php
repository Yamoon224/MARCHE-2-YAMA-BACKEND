<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Support\Facades\DB;

final class AuditTrail
{
    private const SECRET_KEYS = '/pass|token|secret|confirmation_code|card|otp|authorization|pin$/i';

    private const MAX_PAYLOAD = 20000;

    /** @param  array<string, mixed>  $payload */
    public function record(int $userId, string $method, string $path, int $status, ?string $ip, array $payload): AuditLog
    {
        $json = json_encode(self::mask($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
        if (strlen($json) > self::MAX_PAYLOAD) {
            $json = '{"_truncated":true}';
        }
        $path = mb_substr($path, 0, 255);
        $createdAt = now()->format('Y-m-d H:i:s');

        return DB::transaction(function () use ($userId, $method, $path, $status, $ip, $json, $createdAt): AuditLog {
            $previous = AuditLog::query()->orderByDesc('id')->lockForUpdate()->first();
            $previousHash = $previous?->hash;

            return AuditLog::create([
                'user_id' => $userId,
                'method' => $method,
                'path' => $path,
                'status' => $status,
                'ip' => $ip,
                'payload' => $json,
                'created_at' => $createdAt,
                'prev_hash' => $previousHash,
                'hash' => AuditLog::hashOf($previousHash, $userId, $method, $path, $status, $ip, $json, $createdAt),
            ]);
        });
    }

    /**
     * Contrôle toute la chaîne : chaque hachage doit se recalculer et pointer sur le précédent.
     *
     * @return array{valid: bool, checked: int, broken_at_id: int|null}
     */
    public function verify(): array
    {
        $previousHash = null;
        $checked = 0;
        $brokenAt = null;

        foreach (AuditLog::query()->orderBy('id')->lazyById(500) as $log) {
            $expected = AuditLog::hashOf(
                $previousHash, $log->user_id, $log->method, $log->path, $log->status, $log->ip, $log->payload,
                $log->created_at->format('Y-m-d H:i:s'),
            );

            if ($log->prev_hash !== $previousHash || ! hash_equals($expected, $log->hash)) {
                $brokenAt = $log->id;
                break;
            }

            $previousHash = $log->hash;
            $checked++;
        }

        return ['valid' => $brokenAt === null, 'checked' => $checked, 'broken_at_id' => $brokenAt];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function mask(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEYS, $key) === 1) {
                $masked[$key] = '***';
            } elseif (is_array($value)) {
                $masked[$key] = self::mask($value);
            } elseif (is_object($value)) {
                $masked[$key] = '[fichier]';
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
