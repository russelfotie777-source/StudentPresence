<?php

namespace Database\Seeders;

use App\Enums\PresenceState;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Enums\Weekday;
use App\Models\CourseTemplate;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\PresenceEtudiant;
use App\Models\RequeteEnseignant;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\TarifHeure;
use App\Models\User;
use App\Services\SeanceGenerator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de données de démonstration réaliste : les 3 niveaux réels du campus
 * (L1/L2/L3), avec filières/salles/matières de L3 reprises du nom et de la
 * structure de l'ancienne base (la cohorte "Niveau 2" de l'ancienne app est
 * celle qui passe en L3 cette année — voir CLAUDE.md). Étudiants identifiés
 * par matricule (pas par téléphone — seul le personnel Délégué/Enseignant a
 * un vrai numéro), comme dans l'ancienne app. 8 semaines d'historique de
 * séances/présences/salaires à taux variables + une séance "en direct"
 * bornée sur l'heure courante pour tester le pointage tout de suite.
 * Idempotent : rejouable sans dupliquer (updateOrCreate partout, et
 * SeanceGenerator lui-même n'insère jamais deux fois la même séance).
 *
 * php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private SeanceGenerator $generator;

    public function run(): void
    {
        $this->generator = app(SeanceGenerator::class);

        // Le campus n'a que 3 niveaux ; seul le L3 (cohorte active cette
        // année) reçoit un catalogue/historique complet dans cette démo.
        Niveau::updateOrCreate(['nom' => 'L1']);
        Niveau::updateOrCreate(['nom' => 'L2']);
        $niveau = Niveau::updateOrCreate(['nom' => 'L3']);

        $filiereGI = Filiere::updateOrCreate(['nom' => 'Génie Informatique', 'niveau_id' => $niveau->id]);
        $filiereGRT = Filiere::updateOrCreate(['nom' => 'GRT', 'niveau_id' => $niveau->id]);

        // Noms de salles réels de l'ancienne base (pas des libellés inventés).
        $salleA = Salle::updateOrCreate(['nom' => 'A23-FI'], ['filiere_id' => $filiereGI->id, 'formation' => 'FI']);
        $salleB = Salle::updateOrCreate(['nom' => 'D4-FA'], ['filiere_id' => $filiereGRT->id, 'formation' => 'FA']);

        TarifHeure::updateOrCreate(['niveau_id' => $niveau->id], ['tarif_heure' => 3000]);

        // Matières réelles de l'ancienne base (codes/noms repris tels quels).
        $matieres = [
            'GI 321' => 'Mathématiques Discrètes',
            'GI 314' => 'Réseaux et Technologie de Communication',
            'GI 324' => 'Méthodes d\'Optimisation',
            'GI 332' => 'Comptabilité et Méthode de Gestion',
            'GRT 311' => 'Électronique Analogique et Digitale',
            'GRT 321' => 'Transmission sans Fil',
            'GRT 323' => 'Traitement Digital du Signal',
            'GRT 332' => 'Introduction à la Cybersécurité',
        ];
        $matiereModels = collect($matieres)->map(
            fn ($nom, $code) => Matiere::updateOrCreate(['code' => $code], ['nom' => $nom])
        );

        $matiereDemo = Matiere::updateOrCreate(['code' => 'DEMO-LIVE'], ['nom' => 'Séance de démonstration']);

        $profGI1 = $this->teacher('699000010', 'Pr. Étienne Mballa');
        $profGI2 = $this->teacher('699000011', 'Pr. Aïcha Ndoumbé');
        $profGRT = $this->teacher('699000012', 'Pr. Samuel Onana');

        $delegueA = $this->delegue('699000001', 'Délégué A23-FI', $salleA);
        $delegueB = $this->delegue('699000002', 'Délégué D4-FA', $salleB);

        // Étudiants identifiés par matricule (format réel de l'ancienne
        // base : AAI##### — ex. 24I01234), pas par numéro de téléphone.
        $etudiantDemo = $this->etudiant('24I09001', 'Étudiant Démo', $salleA);
        $studentsA = collect([$etudiantDemo])
            ->concat($this->classRoster($salleA, 13, 9100));
        $studentsB = $this->classRoster($salleB, 14, 9200);

        $today = Carbon::today();
        $mondayThisWeek = $today->clone()->startOfWeek(Carbon::MONDAY);

        $semaines = collect(range(-6, 1))->map(function (int $offset) use ($mondayThisWeek) {
            $debut = $mondayThisWeek->clone()->addWeeks($offset);
            $fin = $debut->clone()->addDays(6);

            return Semaine::updateOrCreate(
                ['date_debut' => $debut->toDateString()],
                ['numero' => $debut->isoWeek(), 'date_fin' => $fin->toDateString()],
            );
        });

        $rangeStart = $semaines->first()->date_debut->toDateString();
        $rangeEnd = $semaines->last()->date_fin->toDateString();

        // Deux grilles horaires distinctes par salle : mêmes enseignants des
        // deux côtés, mais jours/créneaux qui ne se chevauchent jamais,
        // sinon SeanceGenerator rejette la moitié des séances pour conflit
        // enseignant (un même prof ne peut pas être dans deux salles à la
        // fois au même horaire).
        $timetableA = [
            Weekday::Lundi->value => ['matiere' => 'GI 321', 'prof' => $profGI1, 'debut' => '08:00', 'fin' => '10:00'],
            Weekday::Mardi->value => ['matiere' => 'GI 314', 'prof' => $profGI2, 'debut' => '10:15', 'fin' => '12:15'],
            Weekday::Mercredi->value => ['matiere' => 'GI 324', 'prof' => $profGI2, 'debut' => '08:00', 'fin' => '10:00'],
            Weekday::Jeudi->value => ['matiere' => 'GI 332', 'prof' => $profGRT, 'debut' => '14:00', 'fin' => '15:30'],
            Weekday::Vendredi->value => ['matiere' => 'GI 321', 'prof' => $profGI1, 'debut' => '10:15', 'fin' => '12:15'],
        ];
        $timetableB = [
            Weekday::Lundi->value => ['matiere' => 'GRT 321', 'prof' => $profGI2, 'debut' => '14:00', 'fin' => '16:00'],
            Weekday::Mardi->value => ['matiere' => 'GRT 332', 'prof' => $profGI1, 'debut' => '14:00', 'fin' => '16:00'],
            Weekday::Mercredi->value => ['matiere' => 'GRT 311', 'prof' => $profGI1, 'debut' => '14:00', 'fin' => '16:00'],
            Weekday::Jeudi->value => ['matiere' => 'GRT 323', 'prof' => $profGI2, 'debut' => '08:00', 'fin' => '10:00'],
            Weekday::Vendredi->value => ['matiere' => 'GRT 311', 'prof' => $profGRT, 'debut' => '14:00', 'fin' => '16:00'],
        ];

        $seances = collect();

        foreach ([[$salleA, $timetableA], [$salleB, $timetableB]] as [$salle, $timetable]) {
            foreach ($timetable as $jour => $slot) {
                $template = CourseTemplate::updateOrCreate(
                    [
                        'salle_id' => $salle->id,
                        'jour' => $jour,
                        'matiere_id' => $matiereModels[$slot['matiere']]->id,
                    ],
                    [
                        'enseignant_id' => $slot['prof']->id,
                        'groupe' => 'G1',
                        'heure_debut' => $slot['debut'],
                        'heure_fin' => $slot['fin'],
                        'date_debut' => $rangeStart,
                        'date_fin' => $rangeEnd,
                        'actif' => true,
                    ],
                );

                $seances = $seances->concat($this->generator->generate($template)->created);
            }
        }

        // Séance "en direct", bornée sur l'heure courante, régénérée à
        // chaque exécution pour pouvoir tester le pointage immédiatement.
        Seance::whereHas('courseTemplate', fn ($q) => $q->where('matiere_id', $matiereDemo->id))->delete();
        CourseTemplate::where('matiere_id', $matiereDemo->id)->delete();

        $liveTemplate = CourseTemplate::create([
            'matiere_id' => $matiereDemo->id,
            'enseignant_id' => $profGRT->id,
            'salle_id' => $salleA->id,
            // Groupe dédié (≠ "G1" des cours réguliers) : évite tout conflit
            // de créneau avec le cours normal de la salle si ce seeder est
            // rejoué au milieu d'un vrai créneau de la grille ci-dessus.
            'groupe' => 'DEMO',
            'jour' => Weekday::fromCarbon(now())->value,
            'heure_debut' => now()->subMinutes(10)->format('H:i'),
            'heure_fin' => now()->addMinutes(50)->format('H:i'),
            'date_debut' => $today->toDateString(),
            'date_fin' => $today->toDateString(),
            'actif' => true,
        ]);
        $liveSeance = $this->generator->generate($liveTemplate)->created->first();

        $studentsBySalle = [$salleA->id => $studentsA, $salleB->id => $studentsB];

        foreach ($seances as $seance) {
            if ($liveSeance && $seance->is($liveSeance)) {
                continue;
            }

            if (! $seance->is_past) {
                continue; // séances d'aujourd'hui (hors direct) et futures : laissées vierges, à faire vivre depuis l'app.
            }

            $this->completePastSeance($seance, $studentsBySalle[$seance->salle_id]);
        }

        $this->seedRequetes($seances->filter(fn (Seance $s) => $s->is_past && $liveSeance && ! $s->is($liveSeance)));

        $this->command?->info('Démo prête : consultez le tableau ci-dessous pour vous connecter.');
        $this->command?->table(
            ['Rôle', 'Identifiant', 'Mot de passe', 'Nom'],
            [
                ['Délégué', '699000001 (téléphone)', 'password', $delegueA->name.' — A23-FI'],
                ['Délégué', '699000002 (téléphone)', 'password', $delegueB->name.' — D4-FA'],
                ['Étudiant', '24I09001 (matricule)', 'password', $etudiantDemo->name.' — A23-FI'],
                ['Enseignant', '699000010 (téléphone)', 'password', $profGI1->name.' (cours normal)'],
                ['Enseignant', '699000012 (téléphone)', 'password', $profGRT->name.' — a une séance EN DIRECT là, maintenant'],
            ],
        );
    }

    private function teacher(string $phone, string $name): User
    {
        return User::updateOrCreate(
            ['phone' => $phone],
            ['name' => $name, 'password' => Hash::make('password'), 'role' => UserRole::Enseignant, 'validation_status' => ValidationStatus::Approved],
        );
    }

    private function delegue(string $phone, string $name, Salle $salle): User
    {
        return User::updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => UserRole::Delegue,
                'validation_status' => ValidationStatus::Approved,
                'formation' => $salle->formation,
                'salle_id' => $salle->id,
                'filiere_id' => $salle->filiere_id,
                'niveau_id' => $salle->filiere->niveau_id,
            ],
        );
    }

    private function etudiant(string $matricule, string $name, Salle $salle): User
    {
        return User::updateOrCreate(
            ['phone' => $matricule],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => UserRole::Etudiant,
                'validation_status' => ValidationStatus::Approved,
                'formation' => $salle->formation,
                'salle_id' => $salle->id,
                'filiere_id' => $salle->filiere_id,
                'niveau_id' => $salle->filiere->niveau_id,
            ],
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function classRoster(Salle $salle, int $count, int $matriculeStart): Collection
    {
        return collect(range(0, $count - 1))->map(
            fn (int $i) => $this->etudiant(
                '24I'.str_pad((string) ($matriculeStart + $i), 5, '0', STR_PAD_LEFT),
                fake()->name(),
                $salle,
            )
        );
    }

    /**
     * Historise une séance passée : marquage délégué/prof avec retard
     * variable (pour obtenir les 4 paliers de salaire), verrouillage du
     * roster et présences étudiants à taux variable (pour une vraie courbe
     * de tendance, pas un plateau à 100%).
     *
     * @param  Collection<int, User>  $students
     */
    private function completePastSeance(Seance $seance, Collection $students): void
    {
        $roll = random_int(1, 100);
        $retardMinutes = match (true) {
            $roll <= 45 => random_int(0, 14),
            $roll <= 70 => random_int(15, 29),
            $roll <= 90 => random_int(30, 39),
            default => random_int(40, 60),
        };

        $profPresent = random_int(1, 100) <= 88;
        $deleguePresent = random_int(1, 100) <= 95;

        $debutReel = $seance->debutPrevu()->addMinutes($retardMinutes);
        $finReelle = $seance->finPrevue()->addMinutes(random_int(-5, 10));
        if ($finReelle->lessThanOrEqualTo($debutReel)) {
            $finReelle = $debutReel->clone()->addMinutes(30);
        }

        $seance->update([
            'etat_delegue' => $deleguePresent ? PresenceState::Present : PresenceState::Absent,
            'etat_prof' => $profPresent ? PresenceState::Present : PresenceState::Absent,
            'debut_reel' => $debutReel->format('H:i:s'),
            'fin_reelle' => $finReelle->format('H:i:s'),
            'presences_locked' => true,
        ]);

        $attendanceRate = random_int(55, 98) / 100;

        foreach ($students as $student) {
            PresenceEtudiant::updateOrCreate(
                ['seance_id' => $seance->id, 'etudiant_id' => $student->id],
                ['etat' => (random_int(1, 100) <= $attendanceRate * 100) ? PresenceState::Present : PresenceState::Absent],
            );
        }
    }

    /**
     * Quelques requêtes enseignants de démonstration, sur des séances où le
     * professeur a été marqué absent — pour peupler la page Requêtes côté
     * app et superprotect/requetes.php côté admin.
     *
     * @param  Collection<int, Seance>  $candidateSeances
     */
    private function seedRequetes(Collection $candidateSeances): void
    {
        $absences = $candidateSeances
            ->filter(fn (Seance $s) => $s->etat_prof === PresenceState::Absent)
            ->values();

        if ($absences->isEmpty()) {
            return;
        }

        $statuts = [RequestStatus::EnAttente, RequestStatus::Acceptee, RequestStatus::Rejetee];

        foreach ($absences->take(3) as $i => $seance) {
            $seance->loadMissing(['salle.filiere.niveau', 'courseTemplate.matiere']);

            RequeteEnseignant::updateOrCreate(
                ['seance_id' => $seance->id, 'enseignant_id' => $seance->enseignant_id],
                [
                    'heure_seance' => $seance->heure_debut,
                    'matiere' => $seance->courseTemplate?->matiere?->nom ?? 'Cours',
                    'salle' => $seance->salle->nom,
                    'niveau' => $seance->salle->filiere->niveau->nom,
                    'penalite' => 1000,
                    'description' => "Panne de véhicule, arrivée tardive sur le campus — j'étais bien présent le reste de la séance.",
                    'statut' => $statuts[$i % 3],
                    'date_creation' => $seance->date_seance,
                    'date_traitement' => $statuts[$i % 3] === RequestStatus::EnAttente ? null : $seance->date_seance,
                ],
            );
        }
    }
}
