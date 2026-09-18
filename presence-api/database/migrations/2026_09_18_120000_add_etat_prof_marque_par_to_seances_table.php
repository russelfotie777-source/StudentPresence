<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qui a posé `etat_prof` : null quand c'est l'enseignant lui-même, le
     * délégué quand il a confirmé à sa place (réglage admin, voir
     * App\Models\Parametre::DELEGUE_CONFIRME_ENSEIGNANT). Cet état pèse sur
     * la paie de l'enseignant : on garde la trace de qui l'a donné.
     */
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->foreignId('etat_prof_marque_par_id')->nullable()->after('etat_prof')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('etat_prof_marque_par_id');
        });
    }
};
