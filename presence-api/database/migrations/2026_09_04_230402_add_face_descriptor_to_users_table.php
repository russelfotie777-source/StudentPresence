<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Second facteur d'authentification (étudiants uniquement) : le
     * descripteur facial est un tableau de 128 nombres calculé côté
     * navigateur (TensorFlow.js), jamais une photo — reste minuscule
     * (quelques centaines d'octets en JSON), aucun souci de volumétrie ni
     * de traitement lourd côté serveur mutualisé.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('face_descriptor')->nullable()->after('password');
            $table->timestamp('face_enrolled_at')->nullable()->after('face_descriptor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['face_descriptor', 'face_enrolled_at']);
        });
    }
};
