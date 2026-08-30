<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un "gabarit" de cours récurrent (matière + enseignant + salle + créneau
     * hebdomadaire). Remplace le couple cours/emplois_temps de l'ancienne app
     * (dont le lien emploi_id était toujours à 0 — jamais réellement utilisé).
     * Le SeanceGenerator matérialise une `seance` par `semaine` couverte par
     * [date_debut, date_fin].
     */
    public function up(): void
    {
        Schema::create('course_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matiere_id')->constrained('matieres')->restrictOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('salle_id')->constrained('salles')->cascadeOnDelete();
            $table->string('groupe', 10)->default('G1');
            $table->string('jour', 10); // App\Enums\Weekday
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_templates');
    }
};
