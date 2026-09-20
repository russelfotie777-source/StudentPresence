<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une matière appartient à une filière — donc à un niveau et à un
     * département : les matières de GI L2 ne sont pas celles de GRT L1.
     * `filiere_id` nul = matière commune à toutes les filières (l'anglais,
     * par exemple) ; l'unicité du code se juge dans la filière.
     *
     * Les matières existantes sont rattachées à la filière que leurs cours
     * révèlent : si tous leurs cours se donnent dans les salles d'une même
     * filière, c'est la leur ; sinon elles restent communes.
     */
    public function up(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->foreignId('filiere_id')->nullable()->after('id')->constrained('filieres')->cascadeOnDelete();
            $table->dropUnique(['code']);
            $table->unique(['filiere_id', 'code']);
        });

        $filieresParMatiere = DB::table('course_templates')
            ->join('salles', 'salles.id', '=', 'course_templates.salle_id')
            ->select('course_templates.matiere_id', DB::raw('MIN(salles.filiere_id) as filiere_min'), DB::raw('MAX(salles.filiere_id) as filiere_max'))
            ->groupBy('course_templates.matiere_id')
            ->get();

        foreach ($filieresParMatiere as $ligne) {
            if ($ligne->filiere_min === $ligne->filiere_max) {
                DB::table('matieres')->where('id', $ligne->matiere_id)->update(['filiere_id' => $ligne->filiere_min]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->dropUnique(['filiere_id', 'code']);
            $table->dropConstrainedForeignId('filiere_id');
            $table->unique('code');
        });
    }
};
