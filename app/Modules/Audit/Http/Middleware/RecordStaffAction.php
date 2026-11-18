<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Middleware;

use App\Modules\Audit\Services\AuditTrail;
use App\Modules\Identity\Contracts\PermissionHolder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Journalise toute requête mutante faite par un compte du personnel authentifié (résultat compris). */
final class RecordStaffAction
{
    public function __construct(private readonly AuditTrail $trail) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $user = $request->user();

        if ($user instanceof PermissionHolder && $user->isStaff()) {
            try {
                $this->trail->record(
                    (int) $user->getAuthIdentifier(),
                    $request->method(),
                    '/'.ltrim($request->path(), '/'),
                    $response->getStatusCode(),
                    $request->ip(),
                    $request->except(['_token']),
                );
            } catch (Throwable $e) {
                // Un incident de journal ne doit pas faire échouer l'action déjà exécutée.
                report($e);
            }
        }

        return $response;
    }
}
