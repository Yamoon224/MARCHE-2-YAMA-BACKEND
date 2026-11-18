<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // user_id : identifiant d'un autre module, sans clé étrangère (frontière de module).
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('method', 10)->index();
            $table->string('path', 255);
            $table->unsignedSmallInteger('status');
            $table->string('ip', 45)->nullable();
            $table->longText('payload'); // JSON sérialisé tel que haché (une colonne JSON réordonnerait les clés)
            $table->dateTime('created_at')->index();
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64)->unique();
        });

        if (DB::getDriverName() === 'mysql') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = 'audit_logs_no_'.strtolower($operation);
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON audit_logs FOR EACH ROW "
                    ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d audit en ecriture seule'");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['update', 'delete'] as $operation) {
                DB::unprepared("DROP TRIGGER IF EXISTS audit_logs_no_{$operation}");
            }
        }

        Schema::dropIfExists('audit_logs');
    }
};
