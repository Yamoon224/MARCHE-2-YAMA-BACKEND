<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse (422) toute entrée qui n'est pas de l'UTF-8 valide, au lieu de laisser MySQL la rejeter par une erreur 500.
 * Les fichiers téléversés ne sont pas concernés : seuls les champs texte (corps, paramètres d'URL) sont contrôlés.
 */
final class RejectInvalidUtf8
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isValid($request->query->all()) || ! $this->isValid($request->request->all())) {
            return new JsonResponse([
                'message' => "Les données envoyées contiennent des caractères d'un encodage invalide (UTF-8 attendu).",
                'errors' => ['encoding' => ['Les données doivent être encodées en UTF-8.']],
            ], 422);
        }

        return $next($request);
    }

    /** @param  array<array-key, mixed>  $values */
    private function isValid(array $values): bool
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && ! mb_check_encoding($key, 'UTF-8')) {
                return false;
            }

            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                return false;
            }

            if (is_array($value) && ! $this->isValid($value)) {
                return false;
            }
        }

        return true;
    }
}
