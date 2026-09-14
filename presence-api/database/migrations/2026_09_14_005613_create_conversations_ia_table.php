<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversations de l'assistant IA du back-office : l'historique complet
 * échangé avec le modèle (blocs texte, appels d'outils et leurs résultats)
 * et les actions qu'il a proposées, en attente de confirmation de l'admin
 * ou déjà appliquées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('titre', 120)->nullable();
            $table->json('messages');
            $table->json('actions');
            $table->timestamps();

            $table->index(['admin_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations_ia');
    }
};
