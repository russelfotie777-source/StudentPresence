<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sécurité du compte : le mot de passe initial (commun à tous les comptes
 * créés par l'administration) doit être remplacé à la première connexion,
 * et une adresse e-mail se vérifie par un code à six chiffres — le même
 * mécanisme sert au mot de passe oublié.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('doit_changer_mot_de_passe')->default(false)->after('password');
        });

        // Un code actif par compte et par usage ; l'adresse visée y est
        // conservée, car users.email ne change qu'une fois le code confirmé.
        Schema::create('codes_email', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('usage', 20);
            $table->string('email');
            $table->string('code_hash');
            $table->dateTime('expire_le');
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'usage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_email');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('doit_changer_mot_de_passe'));
    }
};
