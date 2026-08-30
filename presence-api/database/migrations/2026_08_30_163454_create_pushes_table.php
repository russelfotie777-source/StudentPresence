<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pushes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->unique()->constrained('seances')->cascadeOnDelete();
            $table->unsignedInteger('etudiants_presents');
            $table->string('status', 10)->default('pending'); // App\Enums\PushStatus
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pushes');
    }
};
