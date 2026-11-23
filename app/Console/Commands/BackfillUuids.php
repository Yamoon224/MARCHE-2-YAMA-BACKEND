<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackfillUuids extends Command
{
    protected $signature = 'uuid:backfill
        {table? : Table à traiter (par défaut : toutes celles qui ont une colonne uuid)}
        {--dry-run : Compte les lignes sans UUID sans rien modifier}';

    protected $description = 'Génère un UUID public pour les enregistrements existants dont la colonne uuid est vide';

    public function handle(): int
    {
        $tables = $this->argument('table')
            ? [(string) $this->argument('table')]
            : collect(Schema::getTables())->pluck('name')->reject(fn (string $t) => in_array($t, ['failed_jobs', 'job_batches'], true))->filter(fn (string $t) => Schema::hasColumn($t, 'uuid'))->unique()->values()->all();

        if ($tables === []) {
            $this->warn('Aucune table avec une colonne uuid.');

            return self::SUCCESS;
        }

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
                $this->error("La table « {$table} » n'existe pas ou n'a pas de colonne uuid.");

                return self::FAILURE;
            }

            $missing = DB::table($table)->whereNull('uuid')->count();

            if ($this->option('dry-run') || $missing === 0) {
                $this->line(sprintf('%-28s %d ligne(s) sans UUID', $table, $missing));

                continue;
            }

            // Par lots, sur la clé interne : on peut relancer la commande sans risque (seules les lignes vides sont touchées).
            DB::table($table)->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->whereNull('uuid')->update(['uuid' => (string) Str::uuid()]);
                }
            });

            $this->info(sprintf('%-28s %d UUID généré(s)', $table, $missing));
        }

        return self::SUCCESS;
    }
}
