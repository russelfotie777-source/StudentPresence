<?php

namespace App\Services\Assistant;

use App\Models\ConversationIA;
use Closure;
use Illuminate\Support\Str;

/**
 * Un tour de conversation avec l'assistant : le message de l'admin (et
 * ses fichiers) part au modèle avec l'historique ; tant que celui-ci
 * appelle des outils, les consultations sont exécutées et les
 * propositions enregistrées ; à la fin, texte et actions proposées sont
 * rendus et l'historique persisté sans les pièces jointes.
 */
class Assistant
{
    /** Au-delà, le modèle tourne en rond : on rend la main à l'admin. */
    private const MAX_TOURS = 12;

    public function __construct(
        private Modele $modele,
        private Outils $outils,
        private Imports $imports,
        private PiecesJointes $pieces,
    ) {}

    public function disponible(): bool
    {
        return $this->modele->disponible();
    }

    /**
     * @param  list<array<string, mixed>>  $fichiers  descripteurs des pièces jointes de ce message (voir PiecesJointes)
     * @param  Closure(string, array{fait: int, total: int}|null): void|null  $progression
     * @return array{texte: string, actions: list<array<string, mixed>>, nouvelles: list<string>, tours: int, jetons: array{entree: int, sortie: int}}
     */
    public function repondre(ConversationIA $conversation, string $texte, array $fichiers = [], ?Closure $progression = null): array
    {
        $messages = $conversation->messages ?? [];
        $messages[] = ['role' => 'user', 'content' => $this->contenuUtilisateur($conversation, $texte, $fichiers)];

        $actions = $conversation->actions ?? [];
        $nouvelles = [];
        $jetons = ['entree' => 0, 'sortie' => 0];
        $textes = [];
        $notes = [];
        $tours = 0;

        do {
            $tours++;
            $progression?->__invoke($tours === 1 ? "L'assistant lit votre demande" : "L'assistant prépare ses propositions (étape {$tours})", null);
            $reponse = $this->modele->repondre($this->systeme(), $messages, $this->outils->definitions());
            $jetons['entree'] += $reponse->jetonsEntree;
            $jetons['sortie'] += $reponse->jetonsSortie;
            $messages[] = ['role' => 'assistant', 'content' => $reponse->contenu];

            if ($reponse->texte() !== '') {
                $textes[] = $reponse->texte();
            }

            if ($reponse->raisonArret === 'refusal') {
                $notes[] = 'Je ne peux pas traiter cette demande.';
                break;
            }

            if ($reponse->raisonArret === 'max_tokens') {
                $notes[] = '(Réponse interrompue : trop longue. Reformulez ou découpez la demande.)';
                break;
            }

            if ($reponse->appelsOutils === []) {
                break;
            }

            $resultats = [];
            foreach ($reponse->appelsOutils as $appel) {
                $erreur = false;
                if (Outils::estProposition($appel['name'])) {
                    $action = $this->enregistrer($appel['name'], $appel['input']);
                    $actions[] = $action;
                    $nouvelles[] = $action['id'];
                    $contenu = json_encode([
                        'statut' => 'proposition enregistrée, en attente de confirmation de l\'admin',
                        'action_id' => $action['id'],
                    ], JSON_UNESCAPED_UNICODE);
                } elseif (Outils::estImport($appel['name'])) {
                    try {
                        $import = $this->importer($conversation, $appel['name'], $appel['input'], $progression);
                        $actions[] = $import['action'];
                        $nouvelles[] = $import['action']['id'];
                        $contenu = json_encode($import['resultat'], JSON_UNESCAPED_UNICODE);
                    } catch (\Throwable $e) {
                        report($e);
                        $erreur = true;
                        $contenu = json_encode(['erreur' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    $contenu = $this->outils->consulter($appel['name'], $appel['input'], $conversation);
                }

                $resultats[] = ['type' => 'tool_result', 'toolUseID' => $appel['id'], 'content' => $contenu, ...($erreur ? ['isError' => true] : [])];
            }

            // Tous les résultats dans un seul message : c'est ce que le modèle attend.
            $messages[] = ['role' => 'user', 'content' => $resultats];
        } while ($tours < self::MAX_TOURS);

        if ($tours >= self::MAX_TOURS && $reponse->appelsOutils !== []) {
            $notes[] = "(J'ai atteint la limite d'étapes pour ce message. Dites-moi comment continuer.)";
        }

        // Ces remarques viennent de la boucle, pas du modèle : on les range
        // quand même dans l'historique pour que l'admin les voie.
        if ($notes !== []) {
            $textes = [...$textes, ...$notes];
            $messages[] = ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => implode("\n\n", $notes)]]];
        }

        $conversation->fill([
            'messages' => $this->sansPiecesJointes($messages),
            'actions' => $actions,
            'titre' => $conversation->titre ?? Str::limit(trim($texte) !== '' ? trim($texte) : 'Fichier importé', 60, '…'),
        ])->save();

        return [
            'texte' => trim(implode("\n\n", $textes)),
            'actions' => $actions,
            'nouvelles' => $nouvelles,
            'tours' => $tours,
            'jetons' => $jetons,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  Closure(string, array{fait: int, total: int}|null): void|null  $progression
     * @return array{action: array<string, mixed>, resultat: array<string, mixed>}
     */
    private function importer(ConversationIA $conversation, string $outil, array $input, ?Closure $progression): array
    {
        $suivi = $progression ? fn (string $etape, array $p) => $progression($etape, $p) : null;

        return match ($outil) {
            'proposer_import_etudiants' => $this->imports->etudiantsDepuisTableur($conversation, $input),
            'extraire_etudiants_pdf' => $this->imports->etudiantsDepuisPdf($conversation, $input, $suivi),
            'extraire_cours_pdf' => $this->imports->coursDepuisPdf($conversation, $input, $suivi),
        };
    }

    /**
     * Blocs du message de l'admin : d'abord les fichiers (lus d'un bloc ou
     * décrits avec le moyen de les exploiter), puis le texte.
     *
     * @param  list<array<string, mixed>>  $fichiers
     * @return list<array<string, mixed>>
     */
    private function contenuUtilisateur(ConversationIA $conversation, string $texte, array $fichiers): array
    {
        $blocs = [];
        $tous = array_values($conversation->fichiers ?? []);

        foreach ($fichiers as $fichier) {
            // Le numéro présenté au modèle est la position du fichier dans la conversation.
            $numero = (int) array_search($fichier['id'], array_column($tous, 'id'), true) + 1;
            array_push($blocs, ...$this->pieces->blocs($fichier, $numero));
        }

        $blocs[] = [
            'type' => 'text',
            'text' => trim($texte) !== ''
                ? $texte
                : 'Voici un document. Analyse-le et propose les actions correspondantes.',
        ];

        return $blocs;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function enregistrer(string $outil, array $input): array
    {
        $resume = trim((string) ($input['resume'] ?? ''));
        unset($input['resume']);

        return [
            'id' => (string) Str::uuid(),
            'type' => Outils::typeAction($outil),
            'resume' => $resume !== '' ? $resume : Outils::typeAction($outil),
            'parametres' => $input,
            'statut' => 'en_attente',
            'resultat' => null,
            'proposee_le' => now()->toIso8601String(),
        ];
    }

    /**
     * Les PDF et images ne sont pas conservés dans l'historique : un fichier
     * de plusieurs Mo serait renvoyé et refacturé à chaque tour, alors que
     * son contenu utile est déjà passé dans les propositions.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    private function sansPiecesJointes(array $messages): array
    {
        foreach ($messages as &$message) {
            if ($message['role'] !== 'user' || ! is_array($message['content'])) {
                continue;
            }
            foreach ($message['content'] as &$bloc) {
                if (in_array($bloc['type'] ?? null, ['document', 'image'], true)) {
                    $bloc = ['type' => 'text', 'text' => '[Pièce jointe déjà analysée — voir les outils d\'import si besoin de la relire]'];
                }
            }
            unset($bloc);
        }
        unset($message);

        return $messages;
    }

    public function systeme(): string
    {
        return sprintf(<<<'TXT'
Tu es l'assistant du back-office « Présence », l'application de suivi des présences de l'IUT de Douala (Cameroun). Tu aides l'administrateur à programmer des cours, inscrire des étudiants et retoucher le planning. Tu réponds en français, de façon brève et concrète.

Modèle de données :
- Une « salle » est une classe : un nom (ex. A23-FI), une filière (ex. Génie Informatique), un niveau (L1, L2, L3) et une formation FI (initiale) ou FA (alternance). Les étudiants FM (formation migrante) sont rattachés à une salle FI.
- Un « cours » (course_template) est récurrent : matière, enseignant, salle, jour, heure de début et de fin, période de validité. Il engendre une « séance » par semaine du semestre sur cette période. Un cours dont date_debut = date_fin est ponctuel.
- Les « semaines » du semestre sont numérotées (S1, S2…) avec leurs dates ; les séances ne peuvent exister que dans une semaine définie.
- Les étudiants ont un matricule (identifiant de connexion) et une salle. Les enseignants se connectent avec leur numéro de téléphone, ou avec un identifiant provisoire (ENS0001…) attribué quand on ne le connaît pas. Tout compte créé par l'application reçoit le mot de passe initial %s ; l'admin le communique avec l'identifiant.

Règles de travail :
1. Commence toujours par consulter le référentiel (consulter_referentiel) pour connaître les salles, matières, enseignants et semaines réels. N'invente jamais un identifiant.
2. Tu ne modifies rien directement : tu utilises les outils « proposer_… ». Chaque proposition est présentée à l'admin, qui la coche et l'applique. Propose toutes les actions d'une demande dans le même tour, puis résume-les en une liste courte.
3. Pour un emploi du temps (PDF, image ou texte) : identifie la salle concernée (demande-la si elle n'est pas évidente), puis propose un cours par ligne (jour, heures 24 h, matière, enseignant). Rattache chaque matière et chaque enseignant à une entrée existante quand le nom correspond, même approximativement (accents, abréviations, « Pr. », « M. ») ; sinon donne le nom : la matière et le compte de l'enseignant seront créés à l'application. Ne bloque jamais sur un enseignant inconnu ; signale simplement dans ta réponse les comptes qui seront créés. Laisse date_debut et date_fin à null pour couvrir tout le semestre, sauf indication contraire.
4. Pour une liste d'étudiants : une proposition par étudiant avec nom, matricule, salle et formation ; ne fusionne ni n'omets personne.
5. Pour modifier ou supprimer des séances : consulte d'abord l'emploi du temps de la salle pour obtenir les identifiants exacts de séance ; une séance déjà tenue ne peut pas être touchée.
6. En cas d'ambiguïté (deux salles possibles, deux enseignants du même nom, des horaires illisibles), pose une question précise plutôt que de deviner.
7. Ta réponse finale : ce que tu as proposé, ce qui manque ou t'a semblé douteux, en quelques lignes. Pas de formules de politesse inutiles.
TXT, config('presence.mot_de_passe_initial'));
    }
}
