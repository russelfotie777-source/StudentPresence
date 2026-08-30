<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences_etudiants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('users')->cascadeOnDelete();
            $table->string('etat', 10); // App\Enums\PresenceState
            $table->timestamp('date_marquage')->useCurrent();
            $table->timestamps();

            $table->unique(['seance_id', 'etudiant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences_etudiants');
    }
};
