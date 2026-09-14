<?php

namespace App\Services\Assistant;

use App\Enums\UserRole;
use App\Models\CourseTemplate;
use App\Models\Matiere;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use App\Services\FeuilleDePresence;

/**
 * Les outils mis à disposition du modèle. Deux familles :
 *
 *  - consultation : exécutés immédiatement, ils lui donnent les identifiants
 *    réels (salles, matières, enseignants, semaines, séances, étudiants) pour
 *    qu'il n'invente rien ;
 *  - proposition : rien n'est écrit. Chaque appel devient une action en
 *    attente que l'admin voit, coche et applique — ou non. C'est ce qui
 *    permet de lui confier un emploi du temps entier sans crainte.
 */
class Outils
{
    public const PREFIXE_PROPOSITION = 'proposer_';

    /** Types d'action que l'admin peut appliquer, un par outil de proposition. */
    public const ACTIONS = [
        'creer_cours', 'inscrire_etudiant', 'creer_enseignant', 'modifier_seance',
        'supprimer_seance', 'supprimer_cours', 'changer_salle_etudiant',
    ];

    public function __construct(private FeuilleDePresence $feuille) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        $entier = fn (string $d) => ['type' => 'integer', 'description' => $d];
        $texte = fn (string $d) => ['type' => 'string', 'description' => $d];
        $texteOuNul = fn (string $d) => ['type' => ['string', 'null'], 'description' => $d];
        $entierOuNul = fn (string $d) => ['type' => ['integer', 'null'], 'description' => $d];
        $heure = fn (string $d) => ['type' => 'string', 'pattern' => '^([01]\d|2[0-3]):[0-5]\d$', 'description' => $d.' (HH:MM, 24 h)'];
        // Les motifs (pattern) ne s'appliquent qu'aux chaînes : un champ
        // optionnel est déclaré comme union chaîne-ou-null via anyOf.
        $dateOuNul = fn (string $d) => ['anyOf' => [['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'], ['type' => 'null']], 'description' => $d.' (AAAA-MM-JJ, ou null)'];
        $heureOuNul = fn (string $d) => ['anyOf' => [['type' => 'string', 'pattern' => '^([01]\d|2[0-3]):[0-5]\d$'], ['type' => 'null']], 'description' => $d.' (HH:MM, ou null)'];
        $jour = ['type' => 'string', 'enum' => ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI', 'DIMANCHE'], 'description' => 'Jour de la semaine'];
        $schema = fn (array $props) => [
            'type' => 'object',
            'properties' => $props,
            'required' => array_keys($props),
            'additionalProperties' => false,
        ];

        return [
            [
                'name' => 'consulter_referentiel',
                'description' => "Salles (classes), matières, enseignants et semaines du semestre avec leurs identifiants. À appeler avant toute proposition : n'utilise jamais un identifiant qui n'en vient pas.",
                'strict' => true,
                'inputSchema' => $schema([
                    'partie' => ['type' => 'string', 'enum' => ['tout', 'salles', 'matieres', 'enseignants', 'semaines'], 'description' => 'Quelle partie du référentiel renvoyer'],
                ]),
            ],
            [
                'name' => 'rechercher_etudiants',
                'description' => 'Étudiants et délégués par nom ou matricule, éventuellement limités à une salle.',
                'strict' => true,
                'inputSchema' => $schema([
                    'recherche' => $texteOuNul('Fragment de nom ou de matricule ; null pour tous les étudiants de la salle'),
                    'salle_id' => $entierOuNul('Limiter à cette salle'),
                ]),
            ],
            [
                'name' => 'consulter_emploi_du_temps',
                'description' => "Séances d'une salle sur une semaine (par défaut la semaine en cours), avec leurs identifiants, et les cours récurrents de la salle.",
                'strict' => true,
                'inputSchema' => $schema([
                    'salle_id' => $entier('Salle concernée'),
                    'semaine_id' => $entierOuNul('Semaine ; null pour la semaine en cours'),
                ]),
            ],
            [
                'name' => 'proposer_creer_cours',
                'description' => "Propose un cours pour une salle : une séance chaque semaine (ou une seule si date_debut = date_fin). Un par ligne d'emploi du temps. Matière et enseignant : donne l'identifiant s'il existe, sinon le nom (et le code de matière) pour création.",
                'strict' => true,
                'inputSchema' => $schema([
                    'resume' => $texte("Une phrase pour l'admin, qui décrit exactement l'action (ex. « Maths Discrètes avec Pr. Mballa, lundi 08:00–10:00 en A23-FI, tout le semestre »)"),
                    'salle_id' => $entier('Salle (classe) qui suit ce cours'),
                    'matiere_id' => $entierOuNul('Matière existante'),
                    'matiere_nom' => $texteOuNul("Nom de la matière si elle n'existe pas encore"),
                    'matiere_code' => $texteOuNul("Code de la matière si elle n'existe pas encore (ex. INF321)"),
                    'enseignant_id' => $entierOuNul('Enseignant existant'),
                    'enseignant_nom' => $texteOuNul("Nom de l'enseignant s'il n'est pas dans le référentiel (l'admin devra le créer)"),
                    'jour' => $jour,
                    'heure_debut' => $heure('Début'),
                    'heure_fin' => $heure('Fin'),
                    'date_debut' => $dateOuNul('Première date de validité ; null = début du semestre'),
                    'date_fin' => $dateOuNul('Dernière date de validité ; null = fin du semestre'),
                ]),
            ],
            [
                'name' => 'proposer_inscrire_etudiant',
                'description' => "Propose la création d'un compte étudiant dans une salle. Le matricule sert d'identifiant de connexion ; un mot de passe initial sera généré.",
                'strict' => true,
                'inputSchema' => $schema([
                    'resume' => $texte("Une phrase pour l'admin, qui décrit exactement l'action (ex. « Maths Discrètes avec Pr. Mballa, lundi 08:00–10:00 en A23-FI, tout le semestre »)"),
                    'nom' => $texte('Nom et prénoms'),
                    'matricule' => $texte('Matricule (identifiant de connexion)'),
                    'salle_id' => $entier('Salle de rattachement'),
                    'formation' => ['type' => 'string', 'enum' => ['FI', 'FA', 'FM'], 'description' => 'FI ou FM dans une salle FI, FA dans une salle FA'],
                    'email' => $texteOuNul('Adresse e-mail si connue'),
                ]),
            ],
            [
                'name' => 'proposer_creer_enseignant',
                'description' => "Propose la création d'un compte enseignant validé. Le numéro de téléphone est l'identifiant de connexion : demande-le à l'admin s'il manque.",
                'strict' => true,
                'inputSchema' => $schema([
                    'resume' => $texte("Une phrase pour l'admin, qui décrit exactement l'action (ex. « Maths Discrètes avec Pr. Mballa, lundi 08:00–10:00 en A23-FI, tout le semestre »)"),
                    'nom' => $texte('Nom et prénoms'),
                    'telephone' => $texte('Téléphone (identifiant de connexion)'),
                    'email' => $texteOuNul('Adresse e-mail si connue'),
                ]),
            ],
            [
                'name' => 'proposer_modifier_seance',
                'description' => "Propose de déplacer une séance (date, horaires), de changer d'enseignant ou de salle. Impossible sur une séance déjà tenue.",
                'strict' => true,
                'inputSchema' => $schema([
                    'resume' => $texte("Une phrase pour l'admin, qui décrit exactement l'action (ex. « Maths Discrètes avec Pr. Mballa, lundi 08:00–10:00 en A23-FI, tout le semestre »)"),
                    'seance_id' => $entier('Séance à retoucher'),
                    'date_seance' => $dateOuNul('Nouvelle date ; null pour ne pas changer'),
                    'heure_debut' => $heureOuNul('Nouveau début ; null pour ne pas changer'),
                    'heure_fin' => $heureOuNul('Nouvelle fin ; null pour ne pas changer'),
                    'enseignant_id' => $entierOuNul('Nouvel enseignant ; null pour ne pas changer'),
                    'salle_id' => $entierOuNul('Nouvelle salle ; null pour ne pas changer'),
                ]),
            ],
            [
                'name' => 'proposer_supprimer_seance',
                'description' => "Propose d'annuler une occurrence de séance (pas le cours). Impossible sur une séance déjà tenue.",
                'strict' => true,
                'inputSchema' => $schema([
                    'resume' => $texte("Une phrase pour l'admin, qui décrit exactement l'action (ex. « Maths Discrètes avec Pr. Mballa, lundi 08:00–10:00 en A23-FI, tout le semestre »)"),
                    'seance_id' => $entier('Séance à annuler'),
                    'motif' => $texteOuNul("Raison, pour l'admin"),
                ]),
            ],
            [
                'name' => 'proposer_supprimer_cours',
                'description' => 'Propose de supprimer un cours récurrent et toutes ses séances à venir (celles déjà tenues sont conservées).',
                'strict' => true,
                'inputSchema' => $schema([
                    'resume' => $texte("Une phrase pour l'admin, qui décrit exactement l'action (ex. « Maths Discrètes avec Pr. Mballa, lundi 08:00–10:00 en A23-FI, tout le semestre »)"),
                    'cours_id' => $entier('Cours récurrent (course_template) à supprimer'),
                    'motif' => $texteOuNul("Raison, pour l'admin"),
                ]),
            ],
            [
                'name' => 'proposer_changer_salle_etudiant',
                'description' => 'Propose de rattacher un étudiant à une autre salle (sa filière, son niveau et sa formation suivent la salle).',
                'strict' => true,
                'inputSchema' => $schema([
                    'resume' => $texte("Une phrase pour l'admin, qui décrit exactement l'action (ex. « Maths Discrètes avec Pr. Mballa, lundi 08:00–10:00 en A23-FI, tout le semestre »)"),
                    'etudiant_id' => $entier('Étudiant'),
                    'salle_id' => $entier('Nouvelle salle'),
                ]),
            ],
        ];
    }

    public static function estProposition(string $nom): bool
    {
        return str_starts_with($nom, self::PREFIXE_PROPOSITION);
    }

    /** Type d'action correspondant à un outil de proposition ("proposer_creer_cours" → "creer_cours"). */
    public static function typeAction(string $nomOutil): string
    {
        return substr($nomOutil, strlen(self::PREFIXE_PROPOSITION));
    }

    /**
     * Exécute un outil de consultation et renvoie son résultat en JSON.
     *
     * @param  array<string, mixed>  $input
     */
    public function consulter(string $nom, array $input): string
    {
        $resultat = match ($nom) {
            'consulter_referentiel' => $this->referentiel($input['partie'] ?? 'tout'),
            'rechercher_etudiants' => $this->etudiants($input['recherche'] ?? null, $input['salle_id'] ?? null),
            'consulter_emploi_du_temps' => $this->emploiDuTemps((int) $input['salle_id'], $input['semaine_id'] ?? null),
            default => ['erreur' => "Outil inconnu : {$nom}"],
        };

        return json_encode($resultat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function referentiel(string $partie): array
    {
        $tout = $partie === 'tout';
        $r = ['aujourdhui' => now()->toDateString(), 'jour' => now()->locale('fr')->dayName];

        if ($tout || $partie === 'salles') {
            $r['salles'] = Salle::with('filiere.niveau')->orderBy('nom')->get()->map(fn (Salle $s) => [
                'id' => $s->id, 'nom' => $s->nom, 'formation' => $s->formation->value,
                'filiere' => $s->filiere?->nom, 'niveau' => $s->filiere?->niveau?->nom,
                'formations_accueillies' => $s->formationsAccueillies(),
            ])->values();
        }
        if ($tout || $partie === 'matieres') {
            $r['matieres'] = Matiere::orderBy('nom')->get(['id', 'code', 'nom'])->values();
        }
        if ($tout || $partie === 'enseignants') {
            $r['enseignants'] = User::where('role', UserRole::Enseignant->value)->orderBy('name')
                ->get(['id', 'name', 'phone', 'validation_status'])->values();
        }
        if ($tout || $partie === 'semaines') {
            $r['semaines'] = Semaine::orderBy('numero')->get(['id', 'numero', 'date_debut', 'date_fin'])->values();
            $r['semaine_en_cours_id'] = Semaine::current()?->id;
        }

        return $r;
    }

    /**
     * @return array<string, mixed>
     */
    private function etudiants(?string $recherche, ?int $salleId): array
    {
        $recherche = trim((string) $recherche);

        $etudiants = User::query()
            ->whereIn('role', [UserRole::Etudiant->value, UserRole::Delegue->value])
            ->with('salle')
            ->when($salleId, fn ($q) => $q->where('salle_id', $salleId))
            ->when($recherche !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$recherche}%")
                ->orWhere('phone', 'like', "%{$recherche}%")))
            ->orderBy('name')
            ->limit(80)
            ->get();

        return [
            'total' => $etudiants->count(),
            'etudiants' => $etudiants->map(fn (User $u) => [
                'id' => $u->id, 'nom' => $u->name, 'matricule' => $u->phone, 'role' => $u->role->value,
                'formation' => $u->formation?->value, 'salle_id' => $u->salle_id, 'salle' => $u->salle?->nom,
                'statut_compte' => $u->statut_compte->value,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emploiDuTemps(int $salleId, ?int $semaineId): array
    {
        $salle = Salle::find($salleId);
        if (! $salle) {
            return ['erreur' => "Salle {$salleId} introuvable."];
        }

        $semaine = $semaineId ? Semaine::find($semaineId) : Semaine::current();

        $seances = $semaine
            ? Seance::with(['courseTemplate.matiere', 'enseignant'])->withCount('presences')
                ->where('salle_id', $salleId)->where('semaine_id', $semaine->id)
                ->orderBy('date_seance')->orderBy('heure_debut')->get()
            : collect();

        $cours = CourseTemplate::with(['matiere', 'enseignant'])->where('salle_id', $salleId)
            ->orderBy('jour')->orderBy('heure_debut')->get();

        return [
            'salle' => ['id' => $salle->id, 'nom' => $salle->nom],
            'semaine' => $semaine ? ['id' => $semaine->id, 'numero' => $semaine->numero, 'du' => $semaine->date_debut->toDateString(), 'au' => $semaine->date_fin->toDateString()] : null,
            'seances' => $seances->map(fn (Seance $s) => [
                'id' => $s->id, 'date' => $s->date_seance?->toDateString(), 'jour' => $s->jour->value,
                'heure_debut' => substr($s->heure_debut, 0, 5), 'heure_fin' => substr($s->heure_fin, 0, 5),
                'matiere' => $s->courseTemplate?->matiere?->nom, 'enseignant' => $s->enseignant?->name,
                'enseignant_id' => $s->enseignant_id, 'cours_id' => $s->course_template_id,
                'statut' => $this->feuille->statut($s),
            ])->values(),
            'cours_recurrents' => $cours->map(fn (CourseTemplate $c) => [
                'id' => $c->id, 'matiere' => $c->matiere?->nom, 'enseignant' => $c->enseignant?->name,
                'jour' => $c->jour->value, 'heure_debut' => substr($c->heure_debut, 0, 5), 'heure_fin' => substr($c->heure_fin, 0, 5),
                'du' => $c->date_debut->toDateString(), 'au' => $c->date_fin->toDateString(),
            ])->values(),
        ];
    }
}
