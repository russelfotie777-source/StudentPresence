<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filieres', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 50);
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['nom', 'niveau_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filieres');
    }
};
