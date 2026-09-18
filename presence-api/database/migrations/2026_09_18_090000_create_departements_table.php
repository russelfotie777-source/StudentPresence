<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le département (Génie Informatique, GRT…) coiffe la structure : il
     * possède des filières, qui possèdent des salles. Jusqu'ici la filière
     * était le sommet, et le nom du département n'existait qu'en dur dans
     * l'en-tête des listes de présence — toutes sortaient donc au nom du
     * Génie Informatique, même celles d'une salle GRT.
     *
     * Les filières existantes sont rattachées à un département par défaut,
     * celui dont l'en-tête était codé en dur, pour que rien ne se retrouve
     * orphelin : l'admin déplace ensuite ce qui doit l'être.
     */
    public function up(): void
    {
        Schema::create('departements', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100)->unique();
            // Sigle ("GI", "GRT") : figure sur les listes et dans les libellés courts.
            $table->string('code', 10)->unique();
            // En-tête bilingue des documents officiels ("Computer Sciences").
            $table->string('nom_en', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('filieres', function (Blueprint $table) {
            $table->foreignId('departement_id')->nullable()->after('id')
                ->constrained('departements')->cascadeOnDelete();
        });

        if (DB::table('filieres')->whereNull('departement_id')->exists()) {
            $defaut = DB::table('departements')->insertGetId([
                'nom' => 'Génie Informatique',
                'code' => 'GI',
                'nom_en' => 'Computer Sciences',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('filieres')->whereNull('departement_id')->update(['departement_id' => $defaut]);
        }

        Schema::table('filieres', function (Blueprint $table) {
            $table->foreignId('departement_id')->nullable(false)->change();
            // Une même filière peut exister dans deux départements ("Informatique"
            // en GI et en GRT) : l'unicité se juge dans le département.
            $table->dropUnique(['nom', 'niveau_id']);
            $table->unique(['departement_id', 'niveau_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::table('filieres', function (Blueprint $table) {
            $table->dropUnique(['departement_id', 'niveau_id', 'nom']);
            $table->unique(['nom', 'niveau_id']);
            $table->dropConstrainedForeignId('departement_id');
        });

        Schema::dropIfExists('departements');
    }
};
