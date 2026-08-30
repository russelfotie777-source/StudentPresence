<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('salle_id')->references('id')->on('salles')->nullOnDelete();
            $table->foreign('niveau_id')->references('id')->on('niveaux')->nullOnDelete();
            $table->foreign('filiere_id')->references('id')->on('filieres')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['salle_id']);
            $table->dropForeign(['niveau_id']);
            $table->dropForeign(['filiere_id']);
        });
    }
};
