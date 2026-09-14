<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le traitement d'un message par l'assistant est asynchrone (lecture d'un
 * PDF de 200 pages, import de 2 000 étudiants) : `traitement` porte son
 * état (en_cours, termine, erreur), l'étape en cours et la progression,
 * que l'interface interroge. `fichiers` liste les pièces jointes stockées
 * pour la conversation (tableurs relus à l'application des imports).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations_ia', function (Blueprint $table) {
            $table->json('traitement')->nullable()->after('actions');
            $table->json('fichiers')->nullable()->after('traitement');
        });
    }

    public function down(): void
    {
        Schema::table('conversations_ia', function (Blueprint $table) {
            $table->dropColumn(['traitement', 'fichiers']);
        });
    }
};
