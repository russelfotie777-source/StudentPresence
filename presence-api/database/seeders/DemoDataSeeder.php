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
 * Jeu de données de démonstration réaliste : niveau L3, deux filières,
 * enseignants/délégués/étudiants nommés (identifiants fixes affichés en fin
 * de commande), 8 semaines d'historique de séances/présences + une séance
 * "en direct" bornée sur l'heure courante pour tester le pointage tout de
 * suite. Idempotent : rejouable sans dupliquer (updateOrCreate partout, et
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

        $niveau = Niveau::updateOrCreate(['nom' => 'L3']);

        $filiereGL = Filiere::updateOrCreate(['nom' => 'Génie Logiciel', 'niveau_id' => $niveau->id]);
        $filiereRT = Filiere::updateOrCreate(['nom' => 'Réseaux & Télécoms', 'niveau_id' => $niveau->id]);

        $salleA = Salle::updateOrCreate(['nom' => 'Amphi A101'], ['filiere_id' => $filiereGL->id, 'formation' => 'FI']);
        $salleB = Salle::updateOrCreate(['nom' => 'Salle B203'], ['filiere_id' => $filiereRT->id, 'formation' => 'FI']);

        TarifHeure::updateOrCreate(['niveau_id' => $niveau->id], ['tarif_heure' => 2500]);

        $matieres = [
            'ALG301' => 'Algorithmique Avancée',
            'BDD301' => 'Bases de Données',
            'RES301' => 'Réseaux Informatiques',
            'ANG301' => 'Anglais Technique',
            'GL301' => 'Génie Logiciel',
        ];
        $matiereModels = collect($matieres)->map(
            fn ($nom, $code) => Matiere::updateOrCreate(['code' => $code], ['nom' => $nom])
        );

        $matiereDemo = Matiere::updateOrCreate(['code' => 'DEMO-LIVE'], ['nom' => 'Séance de démonstration']);

        $profAlgoGL = $this->teacher('699000010', 'Pr. Étienne Mballa');
        $profBddRes = $this->teacher('699000011', 'Pr. Aïcha Ndoumbé');
        $profAngDemo = $this->teacher('699000012', 'Pr. Samuel Onana');

        $delegueA = $this->delegue('699000001', 'Délégué Amphi A101', $salleA);
        $delegueB = $this->delegue('699000002', 'Délégué Salle B203', $salleB);

        $etudiantDemo = $this->etudiant('699000003', 'Étudiant Démo', $salleA);
        $studentsA = collect([$etudiantDemo])
            ->concat($this->classRoster($salleA, 13));
        $studentsB = $this->classRoster($salleB, 14);

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
            Weekday::Lundi->value => ['matiere' => 'ALG301', 'prof' => $profAlgoGL, 'debut' => '08:00', 'fin' => '10:00'],
            Weekday::Mardi->value => ['matiere' => 'BDD301', 'prof' => $profBddRes, 'debut' => '10:15', 'fin' => '12:15'],
            Weekday::Mercredi->value => ['matiere' => 'RES301', 'prof' => $profBddRes, 'debut' => '08:00', 'fin' => '10:00'],
            Weekday::Jeudi->value => ['matiere' => 'ANG301', 'prof' => $profAngDemo, 'debut' => '14:00', 'fin' => '15:30'],
            Weekday::Vendredi->value => ['matiere' => 'GL301', 'prof' => $profAlgoGL, 'debut' => '10:15', 'fin' => '12:15'],
        ];
        $timetableB = [
            Weekday::Lundi->value => ['matiere' => 'RES301', 'prof' => $profBddRes, 'debut' => '14:00', 'fin' => '16:00'],
            Weekday::Mardi->value => ['matiere' => 'GL301', 'prof' => $profAlgoGL, 'debut' => '14:00', 'fin' => '16:00'],
            Weekday::Mercredi->value => ['matiere' => 'ALG301', 'prof' => $profAlgoGL, 'debut' => '14:00', 'fin' => '16:00'],
            Weekday::Jeudi->value => ['matiere' => 'BDD301', 'prof' => $profBddRes, 'debut' => '08:00', 'fin' => '10:00'],
            Weekday::Vendredi->value => ['matiere' => 'ANG301', 'prof' => $profAngDemo, 'debut' => '14:00', 'fin' => '16:00'],
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
            'enseignant_id' => $profAngDemo->id,
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
            ['Rôle', 'Téléphone', 'Mot de passe', 'Nom'],
            [
                ['Délégué', '699000001', 'password', $delegueA->name.' — Amphi A101'],
                ['Délégué', '699000002', 'password', $delegueB->name.' — Salle B203'],
                ['Étudiant', '699000003', 'password', $etudiantDemo->name.' — Amphi A101'],
                ['Enseignant', '699000010', 'password', $profAlgoGL->name.' (séance en direct aujourd\'hui : non, cours normal)'],
                ['Enseignant', '699000012', 'password', $profAngDemo->name.' — a une séance EN DIRECT là, maintenant'],
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

    private function etudiant(string $phone, string $name, Salle $salle): User
    {
        return User::updateOrCreate(
            ['phone' => $phone],
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
    private function classRoster(Salle $salle, int $count): Collection
    {
        return User::factory()->count($count)->etudiant($salle)->create();
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
