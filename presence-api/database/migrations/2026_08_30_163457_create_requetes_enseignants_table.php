<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requetes_enseignants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->time('heure_seance')->nullable();
            $table->string('matiere', 100);
            $table->string('salle', 20);
            $table->string('niveau', 20);
            $table->decimal('penalite', 10, 2)->default(0);
            $table->text('description');
            $table->string('preuve_path')->nullable();
            $table->string('statut', 15)->default('en_attente'); // App\Enums\RequestStatus
            $table->timestamp('date_creation')->useCurrent();
            $table->timestamp('date_traitement')->nullable();
            $table->text('commentaire_admin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requetes_enseignants');
    }
};
