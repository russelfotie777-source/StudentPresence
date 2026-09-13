<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horodatage des rappels de pointage envoyés pour une séance : au délégué
 * (envoyer la position), aux étudiants à l'ouverture du pointage, puis en
 * dernière chance avant la fermeture. Un rappel n'est envoyé qu'une fois,
 * quel que soit le nombre de passages du planificateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->timestamp('rappel_delegue_at')->nullable()->after('commentaires');
            $table->timestamp('rappel_ouverture_at')->nullable()->after('rappel_delegue_at');
            $table->timestamp('rappel_cloture_at')->nullable()->after('rappel_ouverture_at');
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dropColumn(['rappel_delegue_at', 'rappel_ouverture_at', 'rappel_cloture_at']);
        });
    }
};
