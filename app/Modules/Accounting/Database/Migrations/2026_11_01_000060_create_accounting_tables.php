<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Plan comptable de départ (SYSCOHADA révisé), extensible depuis l'administration. */
    private const ACCOUNTS = [
        ['411', 'Clients', 'asset'],
        ['4431', 'TVA facturée sur ventes', 'liability'],
        ['521', 'Banques', 'asset'],
        ['571', 'Caisse', 'asset'],
        ['5851', 'Mobile money', 'asset'],
        ['701', 'Ventes de marchandises', 'revenue'],
    ];

    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name');
            $table->string('type', 20);
            $table->timestamps();
        });

        // source_type + source_id : idempotence (une facture ou un encaissement n'est comptabilisé qu'une fois).
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal', 10);
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->string('description');
            $table->date('entry_date');
            $table->unsignedBigInteger('reversal_of_id')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries');
            $table->string('account_code', 10);
            $table->unsignedBigInteger('debit_minor')->default(0);
            $table->unsignedBigInteger('credit_minor')->default(0);

            $table->foreign('account_code')->references('code')->on('accounts');
            $table->index('account_code');
        });

        $now = now();
        DB::table('accounts')->insert(array_map(
            fn (array $a) => ['code' => $a[0], 'name' => $a[1], 'type' => $a[2], 'created_at' => $now, 'updated_at' => $now],
            self::ACCOUNTS,
        ));

        // Grand livre inviolable : refusé par la base elle-même, pas seulement par l'application.
        if (DB::getDriverName() === 'mysql') {
            foreach (['journal_entries', 'journal_lines'] as $table) {
                foreach (['UPDATE', 'DELETE'] as $operation) {
                    $name = $table.'_no_'.strtolower($operation);
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW "
                        ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Grand livre en ecriture seule : contre-passer au lieu de modifier'");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['journal_entries', 'journal_lines'] as $table) {
                foreach (['update', 'delete'] as $operation) {
                    DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$operation}");
                }
            }
        }

        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
