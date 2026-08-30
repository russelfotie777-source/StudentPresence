<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_template_id')->nullable()->constrained('course_templates')->nullOnDelete();
            $table->foreignId('semaine_id')->nullable()->constrained('semaines')->nullOnDelete();
            $table->foreignId('salle_id')->constrained('salles')->restrictOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->restrictOnDelete();
            $table->string('groupe', 10)->default('G1');
            $table->date('date_seance')->nullable();
            $table->string('jour', 10); // App\Enums\Weekday
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->time('debut_reel')->nullable();
            $table->time('fin_reelle')->nullable();
            $table->string('etat_delegue', 10)->nullable(); // App\Enums\PresenceState
            $table->string('etat_prof', 10)->nullable(); // App\Enums\PresenceState
            // Colonne générée (identique à l'ancienne app) : présent seulement
            // si délégué ET enseignant ont tous les deux marqué "present".
            $table->string('etat_final', 10)
                ->storedAs("CASE WHEN etat_delegue = 'present' AND etat_prof = 'present' THEN 'present' ELSE 'absent' END");
            // Remplace le détournement de `commentaires` comme flag de verrouillage
            // dans l'ancienne app : ici un vrai booléen, `commentaires` redevient
            // un champ de notes libres.
            $table->boolean('presences_locked')->default(false);
            $table->text('commentaires')->nullable();
            $table->timestamps();

            $table->index(['salle_id', 'semaine_id', 'jour']);
            $table->index(['enseignant_id', 'date_seance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
