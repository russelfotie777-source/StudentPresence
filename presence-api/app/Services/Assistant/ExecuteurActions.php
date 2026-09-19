<?php

namespace App\Services\Assistant;

use App\Enums\FormationType;
use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Enums\Weekday;
use App\Models\CourseTemplate;
use App\Models\Matiere;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use App\Services\RetouchesPlanning;
use App\Services\SeanceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Applique une action proposée par l'assistant, une fois cochée par
 * l'admin. Chaque type passe par les mêmes règles que l'écran équivalent
 * du back-office (validation, conflits de créneau, séances figées) : le
 * modèle ne dispose d'aucun raccourci.
 */
class ExecuteurActions
{
    public function __construct(
        private SeanceGenerator $generateur,
        private RetouchesPlanning $retouches,
    ) {}

    /**
     * @param  array{type: string, parametres: array<string, mixed>}  $action
     * @return array{ok: bool, message: string, details: array<string, mixed>}
     */
    public function executer(array $action): array
    {
        // Les imports en masse se traitent ligne par ligne, chacune dans sa
        // propre transaction : un doublon ne doit pas annuler 1 999 inscriptions.
        if ($action['type'] === 'importer_etudiants') {
            return $this->importerEtudiants($action['parametres']);
        }
        if ($action['type'] === 'importer_cours') {
            return $this->importerCours($action['parametres']);
        }

        try {
            $details = DB::transaction(fn () => match ($action['type']) {
                'creer_cours' => $this->creerCours($action['parametres']),
                'inscrire_etudiant' => $this->inscrireEtudiant($action['parametres']),
                'creer_enseignant' => $this->creerEnseignant($action['parametres']),
                'modifier_seance' => $this->modifierSeance($action['parametres']),
                'supprimer_seance' => $this->supprimerSeance($action['parametres']),
                'supprimer_cours' => $this->supprimerCours($action['parametres']),
                'changer_salle_etudiant' => $this->changerSalle($action['parametres']),
                default => throw ValidationException::withMessages(['type' => ["Action inconnue : {$action['type']}"]]),
            });

            return ['ok' => true, 'message' => $details['message'], 'details' => $details];
        } catch (ValidationException $e) {
            return [
                'ok' => false,
                'message' => collect($e->errors())->flatten()->implode(' '),
                'details' => ['erreurs' => $e->errors()],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function creerCours(array $p): array
    {
        $bornes = [Semaine::min('date_debut'), Semaine::max('date_fin')];

        $data = Validator::make([
            'salle_id' => $p['salle_id'] ?? null,
            'jour' => $p['jour'] ?? null,
            'heure_debut' => $p['heure_debut'] ?? null,
            'heure_fin' => $p['heure_fin'] ?? null,
            'date_debut' => $p['date_debut'] ?? $bornes[0],
            'date_fin' => $p['date_fin'] ?? $bornes[1],
        ], [
            'salle_id' => ['required', 'exists:salles,id'],
            'jour' => ['required', Rule::in(array_column(Weekday::cases(), 'value'))],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
        ], [], [
            'date_debut' => 'date de début', 'date_fin' => 'date de fin',
        ])->validate();

        if (! $bornes[0]) {
            throw ValidationException::withMessages(['semaines' => ["Aucune semaine du semestre n'est définie : créez d'abord le calendrier."]]);
        }

        $salle = Salle::with('filiere')->findOrFail($data['salle_id']);
        $matiere = $this->matiere($p, $salle->filiere_id);
        [$enseignant, $enseignantCree] = $this->enseignant($p);

        $cours = CourseTemplate::create([
            ...$data,
            'matiere_id' => $matiere->id,
            'enseignant_id' => $enseignant->id,
        ]);

        $resultat = $this->generateur->generate($cours);

        if ($resultat->created->isEmpty()) {
            throw ValidationException::withMessages([
                'seances' => $resultat->skipped->isEmpty()
                    ? ['Aucune semaine du semestre ne couvre la période choisie.']
                    : $resultat->skipped->pluck('reason')->unique()->values()->all(),
            ]);
        }

        return [
            'message' => sprintf(
                '%s avec %s, %s %s–%s : %d séance%s créée%s%s%s.',
                $matiere->nom, $enseignant->name, Str::lower($data['jour']), $data['heure_debut'], $data['heure_fin'],
                $resultat->created->count(), $resultat->created->count() > 1 ? 's' : '', $resultat->created->count() > 1 ? 's' : '',
                $resultat->skipped->isNotEmpty() ? ', '.$resultat->skipped->count().' semaine(s) ignorée(s) (conflit ou déjà programmée)' : '',
                $enseignantCree ? " ; compte enseignant créé pour {$enseignant->name} ({$enseignant->phone})" : '',
            ),
            'cours_id' => $cours->id,
            'seances_creees' => $resultat->created->count(),
            'semaines_ignorees' => $resultat->skipped->values()->all(),
            'enseignant_cree' => $enseignantCree ? $this->identifiants($enseignant) : null,
        ];
    }

    /**
     * Matière par identifiant, sinon par code ou nom (insensible à la casse)
     * parmi celles de la filière de la salle et les communes, sinon créée
     * dans la filière de la salle — un emploi du temps importé cite souvent
     * des matières que le catalogue n'a pas encore, et chaque filière a les
     * siennes.
     *
     * @param  array<string, mixed>  $p
     */
    private function matiere(array $p, ?int $filiereId): Matiere
    {
        if (! empty($p['matiere_id'])) {
            return Matiere::find($p['matiere_id'])
                ?? throw ValidationException::withMessages(['matiere_id' => ["Matière {$p['matiere_id']} introuvable."]]);
        }

        $nom = trim((string) ($p['matiere_nom'] ?? ''));
        $code = trim((string) ($p['matiere_code'] ?? ''));

        if ($nom === '' && $code === '') {
            throw ValidationException::withMessages(['matiere' => ['Aucune matière indiquée.']]);
        }

        $candidates = Matiere::query()->pourFiliere($filiereId);
        $existante = ($code !== '' ? (clone $candidates)->whereRaw('LOWER(code) = ?', [Str::lower($code)])->first() : null)
            ?? ($nom !== '' ? (clone $candidates)->whereRaw('LOWER(nom) = ?', [Str::lower($nom)])->first() : null);

        if ($existante) {
            return $existante;
        }

        return Matiere::create([
            'filiere_id' => $filiereId,
            'nom' => $nom !== '' ? $nom : $code,
            'code' => $code !== '' ? $this->codeLibre(Str::upper($code), $filiereId) : $this->codeDepuisNom($nom, $filiereId),
        ]);
    }

    private function codeDepuisNom(string $nom, ?int $filiereId): string
    {
        $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($nom)) ?: 'MAT', 0, 6));

        return $this->codeLibre($base, $filiereId);
    }

    /** Le code tel quel s'il est libre dans la filière, sinon suffixé (INF101, INF1012, …). */
    private function codeLibre(string $base, ?int $filiereId): string
    {
        $pris = fn (string $code) => Matiere::where('code', $code)
            ->where(fn ($q) => $filiereId ? $q->where('filiere_id', $filiereId) : $q->whereNull('filiere_id'))
            ->exists();
        $code = $base;
        for ($i = 2; $pris($code); $i++) {
            $code = Str::substr($base, 0, 18)."{$i}";
        }

        return $code;
    }

    /**
     * Enseignant par identifiant, sinon par nom — insensible à la casse,
     * aux accents, à l'ordre des mots et aux titres (« Pr. », « M. »), et un
     * nom de famille seul suffit s'il ne désigne qu'un enseignant —, sinon
     * créé : un emploi du temps cite des enseignants que l'application ne
     * connaît pas encore, et l'admin n'a pas à les saisir un à un avant de
     * pouvoir l'importer. Sans téléphone, le compte reçoit un identifiant
     * provisoire (ENS0001…) qu'il suffit de communiquer avec le mot de
     * passe initial.
     *
     * @param  array<string, mixed>  $p
     * @return array{0: User, 1: bool} l'enseignant, et s'il vient d'être créé
     */
    private function enseignant(array $p): array
    {
        if (! empty($p['enseignant_id'])) {
            $u = User::where('role', UserRole::Enseignant->value)->find($p['enseignant_id']);

            return [$u ?? throw ValidationException::withMessages(['enseignant_id' => ["Enseignant {$p['enseignant_id']} introuvable."]]), false];
        }

        $nom = self::sansTitre(trim((string) ($p['enseignant_nom'] ?? '')));
        if ($nom === '') {
            throw ValidationException::withMessages(['enseignant' => ['Aucun enseignant indiqué.']]);
        }

        if ($existant = $this->enseignantParNom($nom)) {
            return [$existant, false];
        }

        return [$this->nouvelEnseignant($nom, $p['enseignant_telephone'] ?? null, null), true];
    }

    /**
     * Le nom cité correspond-il à un enseignant connu ? Même nom aux titres,
     * accents et ordre près ; ou une partie du nom (« Mballa » pour
     * « Étienne Mballa ») si elle ne désigne qu'une seule personne — à deux,
     * on ne devine pas.
     */
    private function enseignantParNom(string $nom): ?User
    {
        $cle = self::cleNom($nom);
        if ($cle === []) {
            return null;
        }

        $enseignants = User::where('role', UserRole::Enseignant->value)->get();

        if ($exact = $enseignants->first(fn (User $u) => self::cleNom($u->name) === $cle)) {
            return $exact;
        }

        $partiels = $enseignants->filter(fn (User $u) => array_diff($cle, self::cleNom($u->name)) === []);
        if ($partiels->count() > 1) {
            throw ValidationException::withMessages(['enseignant' => [sprintf(
                '« %s » peut désigner %s : précisez l\'identifiant de l\'enseignant.',
                $nom, $partiels->map(fn (User $u) => "{$u->name} (id {$u->id})")->implode(' ou '),
            )]]);
        }

        return $partiels->first();
    }

    /**
     * Les mots significatifs d'un nom, triés : « Pr. MBALLA Étienne » et
     * « étienne mballa » donnent la même clé.
     *
     * @return list<string>
     */
    private static function cleNom(string $nom): array
    {
        $mots = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($nom)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $mots = array_values(array_diff($mots, self::TITRES));
        sort($mots);

        return $mots;
    }

    /** Titres et civilités qui précèdent un nom dans un emploi du temps. */
    private const TITRES = ['pr', 'prof', 'professeur', 'dr', 'docteur', 'm', 'mr', 'mme', 'mlle', 'monsieur', 'madame', 'ing', 'ir'];

    /** « Pr. Mballa » → « Mballa » : le compte porte le nom, pas le titre. */
    private static function sansTitre(string $nom): string
    {
        return trim((string) preg_replace('/^(?:'.implode('|', self::TITRES).')\.?\s+/i', '', $nom));
    }

    /**
     * Crée un compte enseignant validé, avec le mot de passe initial commun
     * et, faute de téléphone, un identifiant provisoire.
     */
    private function nouvelEnseignant(string $nom, ?string $telephone, ?string $email): User
    {
        $telephone = trim((string) $telephone);

        $data = Validator::make([
            'name' => $nom,
            'phone' => $telephone !== '' ? $telephone : $this->identifiantProvisoire(),
            'email' => $email,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [], ['phone' => 'téléphone', 'name' => 'nom'])->validate();

        return User::create([
            ...$data,
            'password' => Hash::make(self::motDePasseInitial()),
            'role' => UserRole::Enseignant,
            'validation_status' => ValidationStatus::Approved,
        ]);
    }

    /** ENS0001, ENS0002… : le premier numéro libre après le plus haut attribué. */
    private function identifiantProvisoire(): string
    {
        $prefixe = (string) config('presence.prefixe_identifiant_enseignant', 'ENS');
        $dernier = (int) User::where('phone', 'like', $prefixe.'%')->pluck('phone')
            ->map(fn (string $p) => (int) substr($p, strlen($prefixe)))->max();

        do {
            $identifiant = $prefixe.str_pad((string) ++$dernier, 4, '0', STR_PAD_LEFT);
        } while (User::where('phone', $identifiant)->exists());

        return $identifiant;
    }

    private static function motDePasseInitial(): string
    {
        return (string) config('presence.mot_de_passe_initial', '12345678');
    }

    /**
     * Ce que l'admin remet à la personne pour sa première connexion.
     *
     * @return array{nom: string, identifiant: string, mot_de_passe_initial: string}
     */
    private function identifiants(User $u): array
    {
        return ['nom' => $u->name, 'identifiant' => $u->phone, 'mot_de_passe_initial' => self::motDePasseInitial()];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function inscrireEtudiant(array $p): array
    {
        $data = Validator::make([
            'name' => trim((string) ($p['nom'] ?? '')),
            'phone' => trim((string) ($p['matricule'] ?? '')),
            'salle_id' => $p['salle_id'] ?? null,
            'formation' => $p['formation'] ?? null,
            'email' => $p['email'] ?? null,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'salle_id' => ['required', 'exists:salles,id'],
            'formation' => ['required', Rule::enum(FormationType::class)],
            'email' => ['nullable', 'email', 'max:255'],
        ], [], ['phone' => 'matricule', 'name' => 'nom'])->validate();

        $salle = Salle::with('filiere')->findOrFail($data['salle_id']);

        if (! in_array($data['formation'], $salle->formationsAccueillies(), true)) {
            throw ValidationException::withMessages([
                'formation' => ["La salle {$salle->nom} ({$salle->formation->value}) n'accueille pas la formation {$data['formation']}."],
            ]);
        }

        $etudiant = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make(self::motDePasseInitial()),
            'role' => UserRole::Etudiant,
            'validation_status' => ValidationStatus::Approved,
            'formation' => $data['formation'],
            'salle_id' => $salle->id,
            'filiere_id' => $salle->filiere_id,
            'niveau_id' => $salle->filiere->niveau_id,
        ]);

        return [
            'message' => "{$etudiant->name} ({$etudiant->phone}) inscrit·e en {$salle->nom}.",
            'etudiant_id' => $etudiant->id,
            'matricule' => $etudiant->phone,
            'mot_de_passe_initial' => self::motDePasseInitial(),
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function creerEnseignant(array $p): array
    {
        $nom = self::sansTitre(trim((string) ($p['nom'] ?? '')));
        if ($nom === '') {
            throw ValidationException::withMessages(['nom' => ['Le nom est obligatoire.']]);
        }

        $enseignant = $this->nouvelEnseignant($nom, $p['telephone'] ?? null, $p['email'] ?? null);

        return [
            'message' => "Compte enseignant créé pour {$enseignant->name} ({$enseignant->phone}).",
            'enseignant_id' => $enseignant->id,
            'telephone' => $enseignant->phone,
            'mot_de_passe_initial' => self::motDePasseInitial(),
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function modifierSeance(array $p): array
    {
        $seance = Seance::find($p['seance_id'] ?? 0)
            ?? throw ValidationException::withMessages(['seance_id' => ['Séance introuvable.']]);

        $changements = Validator::make(array_filter([
            'date_seance' => $p['date_seance'] ?? null,
            'heure_debut' => $p['heure_debut'] ?? null,
            'heure_fin' => $p['heure_fin'] ?? null,
            'enseignant_id' => $p['enseignant_id'] ?? null,
            'salle_id' => $p['salle_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''), [
            'date_seance' => ['sometimes', 'date'],
            'heure_debut' => ['sometimes', 'date_format:H:i'],
            'heure_fin' => ['sometimes', 'date_format:H:i'],
            'enseignant_id' => ['sometimes', Rule::exists('users', 'id')->where('role', 'Enseignant')],
            'salle_id' => ['sometimes', 'exists:salles,id'],
        ])->validate();

        if ($changements === []) {
            throw ValidationException::withMessages(['changements' => ['Aucun changement indiqué.']]);
        }

        $avant = sprintf('%s %s–%s', $seance->date_seance?->format('d/m'), substr($seance->heure_debut, 0, 5), substr($seance->heure_fin, 0, 5));
        $seance = $this->retouches->modifier($seance, $changements);

        return [
            'message' => sprintf(
                '%s déplacée de %s vers %s %s–%s%s.',
                $seance->courseTemplate?->matiere?->nom ?? 'Séance',
                $avant,
                $seance->date_seance->format('d/m'), substr($seance->heure_debut, 0, 5), substr($seance->heure_fin, 0, 5),
                isset($changements['enseignant_id']) ? ', avec '.$seance->enseignant->name : '',
            ),
            'seance_id' => $seance->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function supprimerSeance(array $p): array
    {
        $seance = Seance::with('courseTemplate.matiere')->find($p['seance_id'] ?? 0)
            ?? throw ValidationException::withMessages(['seance_id' => ['Séance introuvable.']]);

        $libelle = sprintf('%s du %s %s', $seance->courseTemplate?->matiere?->nom ?? 'Séance', $seance->date_seance?->format('d/m'), substr($seance->heure_debut, 0, 5));
        $this->retouches->annuler($seance);

        return ['message' => "{$libelle} annulée.", 'seance_id' => $seance->id];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function supprimerCours(array $p): array
    {
        $cours = CourseTemplate::with('matiere')->find($p['cours_id'] ?? 0)
            ?? throw ValidationException::withMessages(['cours_id' => ['Cours introuvable.']]);

        $nom = $cours->matiere?->nom ?? 'Cours';
        $n = $this->retouches->supprimerCours($cours);

        return ['message' => "{$nom} supprimé, {$n} séance(s) à venir retirée(s).", 'seances_supprimees' => $n];
    }

    /** Échecs conservés dans le résultat : au-delà, seul le compte est gardé. */
    private const MAX_ECHECS_DETAILLES = 300;

    /**
     * @param  array<string, mixed>  $p
     * @return array{ok: bool, message: string, details: array<string, mixed>}
     */
    private function importerEtudiants(array $p): array
    {
        $crees = 0;
        $echecs = [];
        $identifiants = [];

        foreach ($p['etudiants'] ?? [] as $ligne) {
            if (($ligne['anomalie'] ?? null) !== null) {
                $echecs[] = ['ligne' => $ligne['ligne'], 'nom' => $ligne['nom'], 'matricule' => $ligne['matricule'], 'motif' => $ligne['anomalie']];

                continue;
            }

            try {
                $r = DB::transaction(fn () => $this->inscrireEtudiant([
                    'nom' => $ligne['nom'], 'matricule' => $ligne['matricule'],
                    'salle_id' => $ligne['salle_id'], 'formation' => $ligne['formation'], 'email' => null,
                ]));
                $crees++;
                $identifiants[] = ['nom' => $ligne['nom'], 'matricule' => $r['matricule'], 'mot_de_passe' => $r['mot_de_passe_initial'], 'salle' => $ligne['salle_nom'] ?? null];
            } catch (ValidationException $e) {
                $echecs[] = ['ligne' => $ligne['ligne'], 'nom' => $ligne['nom'], 'matricule' => $ligne['matricule'], 'motif' => collect($e->errors())->flatten()->implode(' ')];
            }
        }

        $total = count($p['etudiants'] ?? []);

        return [
            'ok' => $crees > 0 || $total === 0,
            'message' => sprintf('%d étudiant%s inscrit%s sur %d%s.', $crees, $crees > 1 ? 's' : '', $crees > 1 ? 's' : '', $total,
                $echecs ? ', '.count($echecs).' ligne'.(count($echecs) > 1 ? 's' : '').' en échec' : ''),
            'details' => [
                'crees' => $crees,
                'total' => $total,
                'echecs' => array_slice($echecs, 0, self::MAX_ECHECS_DETAILLES),
                'echecs_total' => count($echecs),
                // Remis à l'admin une fois (export CSV) : supprimer la conversation les efface.
                'identifiants' => $identifiants,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{ok: bool, message: string, details: array<string, mixed>}
     */
    private function importerCours(array $p): array
    {
        $crees = 0;
        $seances = 0;
        $echecs = [];
        $enseignantsCrees = [];

        foreach ($p['cours'] ?? [] as $i => $ligne) {
            $libelle = sprintf('%s %s %s–%s (%s)', $ligne['matiere_nom'] ?? '?', $ligne['jour'] ?? '?', $ligne['heure_debut'] ?? '?', $ligne['heure_fin'] ?? '?', $ligne['salle_nom'] ?? 'salle ?');

            if (($ligne['anomalie'] ?? null) !== null) {
                $echecs[] = ['source' => $ligne['source'] ?? $i + 1, 'cours' => $libelle, 'motif' => $ligne['anomalie']];

                continue;
            }

            try {
                $r = DB::transaction(fn () => $this->creerCours([
                    'salle_id' => $ligne['salle_id'],
                    'matiere_id' => $ligne['matiere_id'] ?? null, 'matiere_nom' => $ligne['matiere_nom'] ?? null, 'matiere_code' => $ligne['matiere_code'] ?? null,
                    'enseignant_id' => $ligne['enseignant_id'] ?? null, 'enseignant_nom' => $ligne['enseignant_nom'] ?? null,
                    'enseignant_telephone' => $ligne['enseignant_telephone'] ?? null,
                    'jour' => $ligne['jour'], 'heure_debut' => $ligne['heure_debut'], 'heure_fin' => $ligne['heure_fin'],
                    'date_debut' => $ligne['date_debut'] ?? null, 'date_fin' => $ligne['date_fin'] ?? null,
                ]));
                $crees++;
                $seances += $r['seances_creees'];
                if ($r['enseignant_cree']) {
                    $enseignantsCrees[] = $r['enseignant_cree'];
                }
            } catch (ValidationException $e) {
                $echecs[] = ['source' => $ligne['source'] ?? $i + 1, 'cours' => $libelle, 'motif' => collect($e->errors())->flatten()->implode(' ')];
            }
        }

        $total = count($p['cours'] ?? []);

        return [
            'ok' => $crees > 0 || $total === 0,
            'message' => sprintf('%d cours créé%s sur %d (%d séances)%s%s.', $crees, $crees > 1 ? 's' : '', $total, $seances,
                $echecs ? ', '.count($echecs).' en échec' : '',
                $enseignantsCrees ? sprintf(', %d compte%s enseignant créé%s', count($enseignantsCrees), count($enseignantsCrees) > 1 ? 's' : '', count($enseignantsCrees) > 1 ? 's' : '') : ''),
            'details' => [
                'crees' => $crees,
                'seances_creees' => $seances,
                'total' => $total,
                'echecs' => array_slice($echecs, 0, self::MAX_ECHECS_DETAILLES),
                'echecs_total' => count($echecs),
                // À remettre aux intéressés : identifiant provisoire et mot de passe initial.
                'enseignants_crees' => $enseignantsCrees,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function changerSalle(array $p): array
    {
        $etudiant = User::whereIn('role', [UserRole::Etudiant->value, UserRole::Delegue->value])->find($p['etudiant_id'] ?? 0)
            ?? throw ValidationException::withMessages(['etudiant_id' => ['Étudiant introuvable.']]);
        $salle = Salle::with('filiere')->find($p['salle_id'] ?? 0)
            ?? throw ValidationException::withMessages(['salle_id' => ['Salle introuvable.']]);

        if ($salle->id === $etudiant->salle_id) {
            throw ValidationException::withMessages(['salle_id' => ["{$etudiant->name} est déjà en {$salle->nom}."]]);
        }

        $etudiant->update([
            'salle_id' => $salle->id,
            'filiere_id' => $salle->filiere_id,
            'niveau_id' => $salle->filiere->niveau_id,
            'formation' => $salle->formation,
        ]);

        return ['message' => "{$etudiant->name} rattaché·e à {$salle->nom}.", 'etudiant_id' => $etudiant->id];
    }
}
