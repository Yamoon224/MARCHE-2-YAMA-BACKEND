<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        // Plusieurs permissions possibles (`permission:a,b`) : une seule suffit.
        $allowed = $user !== null && array_filter($permissions, fn (string $permission) => $user->hasPermission($permission)) !== [];

        abort_unless($allowed, 403, 'Action non autorisée.');

        return $next($request);
    }
}
