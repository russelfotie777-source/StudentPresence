<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réglages d'exploitation modifiables par l'admin sans redéploiement —
     * table clé/valeur volontairement générique plutôt qu'une table par
     * réglage. Première utilisation : les grades soumis au second facteur
     * facial (voir App\Models\Parametre::FACE_AUTH_ROLES).
     */
    public function up(): void
    {
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique();
            $table->json('valeur');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
    }
};
