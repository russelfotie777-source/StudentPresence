<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Statut d'exploitation d'un compte, distinct de validation_status (qui
     * ne concerne que l'entrée en fonction d'un délégué/enseignant) :
     * restreint = connexion permise mais pointage refusé, bloqué = connexion
     * refusée. Le motif est conservé pour être montré à la personne.
     *
     * Sur presences_etudiants : quand l'admin force un état de présence, on
     * garde qui l'a fait. C'est un pouvoir qui écrase le pointage réel, il
     * doit rester traçable en cas de contestation.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('statut_compte', 12)->default('actif')->after('validation_status'); // App\Enums\StatutCompte
            $table->text('motif_statut')->nullable()->after('statut_compte');
            $table->timestamp('statut_modifie_le')->nullable()->after('motif_statut');
        });

        Schema::table('presences_etudiants', function (Blueprint $table) {
            $table->foreignId('forcee_par_id')->nullable()->after('precision_metres')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presences_etudiants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forcee_par_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['statut_compte', 'motif_statut', 'statut_modifie_le']);
        });
    }
};
