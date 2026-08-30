<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 20);
            $table->foreignId('filiere_id')->constrained('filieres')->cascadeOnDelete();
            $table->string('formation', 2)->default('FI'); // App\Enums\FormationType (FI ou FA)
            $table->timestamps();

            $table->unique(['nom', 'filiere_id', 'formation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salles');
    }
};
