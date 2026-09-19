<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trois traces pour la gestion des étudiants côté admin :
     *
     * - `users.presence_automatique` : privilège accordé par l'admin, l'étudiant
     *   est compté présent à chaque séance de sa salle sans pointer (motif et
     *   date conservés : c'est une faveur, elle doit rester explicable) ;
     * - `presences_etudiants.automatique` : la présence vient de ce privilège,
     *   pas d'un pointage ni d'un choix du délégué ;
     * - `demandes_formation.salle_origine_id` : d'où venait l'étudiant migrant,
     *   figé à l'approbation — sa salle courante devient celle d'accueil.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('presence_automatique')->default(false)->after('motif_statut');
            $table->text('presence_automatique_motif')->nullable()->after('presence_automatique');
            $table->timestamp('presence_automatique_le')->nullable()->after('presence_automatique_motif');
        });

        Schema::table('presences_etudiants', function (Blueprint $table) {
            $table->boolean('automatique')->default(false)->after('forcee_par_id');
        });

        Schema::table('demandes_formation', function (Blueprint $table) {
            $table->foreignId('salle_origine_id')->nullable()->after('salle_cible_id')
                ->constrained('salles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('demandes_formation', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salle_origine_id');
        });
        Schema::table('presences_etudiants', function (Blueprint $table) {
            $table->dropColumn('automatique');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['presence_automatique', 'presence_automatique_motif', 'presence_automatique_le']);
        });
    }
};
