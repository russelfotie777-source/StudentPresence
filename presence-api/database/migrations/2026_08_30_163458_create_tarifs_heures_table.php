<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifs_heures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('niveau_id')->unique()->constrained('niveaux')->cascadeOnDelete();
            $table->decimal('tarif_heure', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifs_heures');
    }
};
