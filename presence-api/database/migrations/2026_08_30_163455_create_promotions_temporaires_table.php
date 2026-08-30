<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions_temporaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('promoteur_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('date_debut')->useCurrent();
            $table->dateTime('date_fin');
            $table->unsignedInteger('duree_minutes');
            $table->timestamps();

            $table->index(['etudiant_id', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions_temporaires');
    }
};
