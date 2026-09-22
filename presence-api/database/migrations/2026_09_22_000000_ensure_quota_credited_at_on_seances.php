<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `quota_credited_at` a été ajoutée après coup dans la migration de création
 * des séances : une base migrée avant ne l'a pas, et créditer les heures
 * d'un enseignant y échoue (« Unknown column 'quota_credited_at' »). On la
 * pose ici si elle manque ; une base neuve l'a déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('seances', 'quota_credited_at')) {
            Schema::table('seances', function (Blueprint $table) {
                $table->timestamp('quota_credited_at')->nullable()->after('fin_reelle');
            });
        }
    }

    public function down(): void
    {
        // Rien : la colonne appartient à la migration de création des séances.
    }
};
