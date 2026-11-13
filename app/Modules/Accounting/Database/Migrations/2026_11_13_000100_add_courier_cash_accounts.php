<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Espèces détenues par les livreurs (avant remise), écarts de caisse (perte / gain). */
    private const ACCOUNTS = [
        ['5715', 'Espèces détenues par les livreurs', 'asset'],
        ['658', 'Écarts de caisse (manquants)', 'expense'],
        ['758', 'Écarts de caisse (excédents)', 'revenue'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ACCOUNTS as [$code, $name, $type]) {
            DB::table('accounts')->updateOrInsert(['code' => $code], ['name' => $name, 'type' => $type, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('code', array_column(self::ACCOUNTS, 0))->delete();
    }
};
