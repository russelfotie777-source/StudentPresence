<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions_seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->unique()->constrained('seances')->cascadeOnDelete();
            $table->foreignId('delegue_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('date_creation')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions_seances');
    }
};
