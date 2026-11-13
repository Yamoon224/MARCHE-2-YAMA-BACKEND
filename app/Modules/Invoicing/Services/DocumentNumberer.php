<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Services;

use Illuminate\Support\Facades\DB;

/** Numérotation séquentielle sans trou, par type de document et par exercice. À appeler dans la transaction du document. */
final class DocumentNumberer
{
    public function next(string $type, string $prefix): string
    {
        $year = (int) now()->format('Y');

        DB::table('invoice_sequences')->insertOrIgnore(['type' => $type, 'year' => $year, 'last_number' => 0]);

        // Ligne verrouillée jusqu'à la fin de la transaction : deux documents simultanés n'obtiennent jamais le même numéro.
        $sequence = DB::table('invoice_sequences')->where('type', $type)->where('year', $year)->lockForUpdate()->first();
        $next = ((int) $sequence->last_number) + 1;

        DB::table('invoice_sequences')->where('id', $sequence->id)->update(['last_number' => $next]);

        return sprintf('%s-%d-%06d', $prefix, $year, $next);
    }
}
