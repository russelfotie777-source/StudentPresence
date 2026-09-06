<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le navigateur fournit, avec chaque position, un rayon d'incertitude en
     * mètres (coords.accuracy) qui n'était jusqu'ici ni transmis ni conservé.
     * Sans lui, comparer une distance à un seuil de 120 m n'a pas de sens :
     * un premier point Wi-Fi/antenne peut porter ±800 m d'incertitude.
     *
     * On conserve aussi la distance calculée au moment du pointage : c'est la
     * seule trace exploitable a posteriori quand un étudiant conteste, la
     * position du délégué pouvant avoir été réécrite depuis.
     */
    public function up(): void
    {
        Schema::table('positions_seances', function (Blueprint $table) {
            $table->unsignedSmallInteger('precision_metres')->nullable()->after('longitude');
        });

        Schema::table('presences_etudiants', function (Blueprint $table) {
            $table->unsignedSmallInteger('distance_metres')->nullable()->after('etat');
            $table->unsignedSmallInteger('precision_metres')->nullable()->after('distance_metres');
        });
    }

    public function down(): void
    {
        Schema::table('positions_seances', function (Blueprint $table) {
            $table->dropColumn('precision_metres');
        });

        Schema::table('presences_etudiants', function (Blueprint $table) {
            $table->dropColumn(['distance_metres', 'precision_metres']);
        });
    }
};
