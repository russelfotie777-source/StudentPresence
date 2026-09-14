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

        $matiere = $this->matiere($p);
        $enseignant = $this->enseignant($p);

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
                '%s avec %s, %s %s–%s : %d séance%s créée%s%s.',
                $matiere->nom, $enseignant->name, Str::lower($data['jour']), $data['heure_debut'], $data['heure_fin'],
                $resultat->created->count(), $resultat->created->count() > 1 ? 's' : '', $resultat->created->count() > 1 ? 's' : '',
                $resultat->skipped->isNotEmpty() ? ', '.$resultat->skipped->count().' semaine(s) ignorée(s) (conflit ou déjà programmée)' : '',
            ),
            'cours_id' => $cours->id,
            'seances_creees' => $resultat->created->count(),
            'semaines_ignorees' => $resultat->skipped->values()->all(),
        ];
    }

    /**
     * Matière par identifiant, sinon par code ou nom (insensible à la casse),
     * sinon créée — un emploi du temps importé cite souvent des matières
     * que le catalogue n'a pas encore.
     *
     * @param  array<string, mixed>  $p
     */
    private function matiere(array $p): Matiere
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

        $existante = Matiere::query()
            ->when($code !== '', fn ($q) => $q->whereRaw('LOWER(code) = ?', [Str::lower($code)]))
            ->when($code === '', fn ($q) => $q->whereRaw('LOWER(nom) = ?', [Str::lower($nom)]))
            ->first()
            ?? ($nom !== '' ? Matiere::whereRaw('LOWER(nom) = ?', [Str::lower($nom)])->first() : null);

        if ($existante) {
            return $existante;
        }

        return Matiere::create([
            'nom' => $nom !== '' ? $nom : $code,
            'code' => $code !== '' ? Str::upper($code) : $this->codeDepuisNom($nom),
        ]);
    }

    private function codeDepuisNom(string $nom): string
    {
        $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($nom)) ?: 'MAT', 0, 6));
        $code = $base;
        for ($i = 2; Matiere::where('code', $code)->exists(); $i++) {
            $code = Str::substr($base, 0, 18)."{$i}";
        }

        return $code;
    }

    /**
     * Enseignant par identifiant, sinon par nom exact — mais jamais créé à
     * la volée : il lui faut un téléphone pour se connecter, c'est l'action
     * creer_enseignant qui s'en charge.
     *
     * @param  array<string, mixed>  $p
     */
    private function enseignant(array $p): User
    {
        if (! empty($p['enseignant_id'])) {
            $u = User::where('role', UserRole::Enseignant->value)->find($p['enseignant_id']);

            return $u ?? throw ValidationException::withMessages(['enseignant_id' => ["Enseignant {$p['enseignant_id']} introuvable."]]);
        }

        $nom = trim((string) ($p['enseignant_nom'] ?? ''));
        $u = $nom !== ''
            ? User::where('role', UserRole::Enseignant->value)->whereRaw('LOWER(name) = ?', [Str::lower($nom)])->first()
            : null;

        return $u ?? throw ValidationException::withMessages([
            'enseignant' => [$nom !== ''
                ? "Enseignant « {$nom} » introuvable : créez son compte d'abord (numéro de téléphone requis)."
                : 'Aucun enseignant indiqué.'],
        ]);
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

        // Mot de passe initial lisible, remis à l'étudiant par l'admin.
        $motDePasse = (string) random_int(10000000, 99999999);

        $etudiant = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($motDePasse),
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
            'mot_de_passe_initial' => $motDePasse,
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function creerEnseignant(array $p): array
    {
        $data = Validator::make([
            'name' => trim((string) ($p['nom'] ?? '')),
            'phone' => trim((string) ($p['telephone'] ?? '')),
            'email' => $p['email'] ?? null,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [], ['phone' => 'téléphone', 'name' => 'nom'])->validate();

        $motDePasse = (string) random_int(10000000, 99999999);

        $enseignant = User::create([
            ...$data,
            'password' => Hash::make($motDePasse),
            'role' => UserRole::Enseignant,
            'validation_status' => ValidationStatus::Approved,
        ]);

        return [
            'message' => "Compte enseignant créé pour {$enseignant->name} ({$enseignant->phone}).",
            'enseignant_id' => $enseignant->id,
            'telephone' => $enseignant->phone,
            'mot_de_passe_initial' => $motDePasse,
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
