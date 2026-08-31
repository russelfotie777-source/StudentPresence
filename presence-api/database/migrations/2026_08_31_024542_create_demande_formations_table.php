<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demande d'un étudiant FA pour basculer vers l'emploi du temps FI
     * (statut FM à l'approbation — voir App\Enums\FormationType::FM). La
     * salle cible est choisie par l'admin au moment de l'approbation, pas
     * par l'étudiant à la création de la demande.
     */
    public function up(): void
    {
        Schema::create('demandes_formation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('salle_cible_id')->nullable()->constrained('salles')->nullOnDelete();
            $table->text('motif')->nullable();
            $table->string('statut', 15)->default('en_attente'); // App\Enums\RequestStatus
            $table->timestamp('date_creation')->useCurrent();
            $table->timestamp('date_traitement')->nullable();
            $table->text('commentaire_admin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_formation');
    }
};
