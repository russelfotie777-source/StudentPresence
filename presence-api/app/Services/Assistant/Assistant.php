<?php

namespace App\Services\Assistant;

use App\Models\ConversationIA;
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

    /** Types MIME acceptés en pièce jointe, et le bloc de contenu qui les porte. */
    public const TYPES_FICHIERS = [
        'application/pdf' => 'document',
        'image/png' => 'image',
        'image/jpeg' => 'image',
        'image/webp' => 'image',
        'image/gif' => 'image',
    ];

    public function __construct(
        private Modele $modele,
        private Outils $outils,
    ) {}

    public function disponible(): bool
    {
        return $this->modele->disponible();
    }

    /**
     * @param  list<array{nom: string, type: string, base64: string}>  $fichiers
     * @return array{texte: string, actions: list<array<string, mixed>>, nouvelles: list<string>, tours: int, jetons: array{entree: int, sortie: int}}
     */
    public function repondre(ConversationIA $conversation, string $texte, array $fichiers = []): array
    {
        $messages = $conversation->messages ?? [];
        $messages[] = ['role' => 'user', 'content' => $this->contenuUtilisateur($texte, $fichiers)];

        $actions = $conversation->actions ?? [];
        $nouvelles = [];
        $jetons = ['entree' => 0, 'sortie' => 0];
        $textes = [];
        $tours = 0;

        do {
            $tours++;
            $reponse = $this->modele->repondre($this->systeme(), $messages, $this->outils->definitions());
            $jetons['entree'] += $reponse->jetonsEntree;
            $jetons['sortie'] += $reponse->jetonsSortie;
            $messages[] = ['role' => 'assistant', 'content' => $reponse->contenu];

            if ($reponse->texte() !== '') {
                $textes[] = $reponse->texte();
            }

            if ($reponse->raisonArret === 'refusal') {
                $textes[] = 'Je ne peux pas traiter cette demande.';
                break;
            }

            if ($reponse->raisonArret === 'max_tokens') {
                $textes[] = '(Réponse interrompue : trop longue. Reformulez ou découpez la demande.)';
                break;
            }

            if ($reponse->appelsOutils === []) {
                break;
            }

            $resultats = [];
            foreach ($reponse->appelsOutils as $appel) {
                if (Outils::estProposition($appel['name'])) {
                    $action = $this->enregistrer($appel['name'], $appel['input']);
                    $actions[] = $action;
                    $nouvelles[] = $action['id'];
                    $contenu = json_encode([
                        'statut' => 'proposition enregistrée, en attente de confirmation de l\'admin',
                        'action_id' => $action['id'],
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    $contenu = $this->outils->consulter($appel['name'], $appel['input']);
                }

                $resultats[] = ['type' => 'tool_result', 'toolUseID' => $appel['id'], 'content' => $contenu];
            }

            // Tous les résultats dans un seul message : c'est ce que le modèle attend.
            $messages[] = ['role' => 'user', 'content' => $resultats];
        } while ($tours < self::MAX_TOURS);

        if ($tours >= self::MAX_TOURS && $reponse->appelsOutils !== []) {
            $textes[] = "(J'ai atteint la limite d'étapes pour ce message. Dites-moi comment continuer.)";
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
     * Blocs du message de l'admin : d'abord les fichiers (PDF en document,
     * images en image), puis le texte.
     *
     * @param  list<array{nom: string, type: string, base64: string}>  $fichiers
     * @return list<array<string, mixed>>
     */
    private function contenuUtilisateur(string $texte, array $fichiers): array
    {
        $blocs = [];

        foreach ($fichiers as $fichier) {
            $type = self::TYPES_FICHIERS[$fichier['type']] ?? null;
            if (! $type) {
                continue;
            }

            $blocs[] = [
                'type' => $type,
                'source' => ['type' => 'base64', 'mediaType' => $fichier['type'], 'data' => $fichier['base64']],
                ...($type === 'document' ? ['title' => $fichier['nom']] : []),
            ];
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
                    $nom = $bloc['title'] ?? ($bloc['type'] === 'image' ? 'image' : 'document');
                    $bloc = ['type' => 'text', 'text' => "[Pièce jointe analysée : {$nom}]"];
                }
            }
            unset($bloc);
        }
        unset($message);

        return $messages;
    }

    public function systeme(): string
    {
        return <<<'TXT'
Tu es l'assistant du back-office « Présence », l'application de suivi des présences de l'IUT de Douala (Cameroun). Tu aides l'administrateur à programmer des cours, inscrire des étudiants et retoucher le planning. Tu réponds en français, de façon brève et concrète.

Modèle de données :
- Une « salle » est une classe : un nom (ex. A23-FI), une filière (ex. Génie Informatique), un niveau (L1, L2, L3) et une formation FI (initiale) ou FA (alternance). Les étudiants FM (formation migrante) sont rattachés à une salle FI.
- Un « cours » (course_template) est récurrent : matière, enseignant, salle, jour, heure de début et de fin, période de validité. Il engendre une « séance » par semaine du semestre sur cette période. Un cours dont date_debut = date_fin est ponctuel.
- Les « semaines » du semestre sont numérotées (S1, S2…) avec leurs dates ; les séances ne peuvent exister que dans une semaine définie.
- Les étudiants ont un matricule (identifiant de connexion) et une salle. Les enseignants ont un numéro de téléphone (identifiant de connexion).

Règles de travail :
1. Commence toujours par consulter le référentiel (consulter_referentiel) pour connaître les salles, matières, enseignants et semaines réels. N'invente jamais un identifiant.
2. Tu ne modifies rien directement : tu utilises les outils « proposer_… ». Chaque proposition est présentée à l'admin, qui la coche et l'applique. Propose toutes les actions d'une demande dans le même tour, puis résume-les en une liste courte.
3. Pour un emploi du temps (PDF, image ou texte) : identifie la salle concernée (demande-la si elle n'est pas évidente), puis propose un cours par ligne (jour, heures 24 h, matière, enseignant). Rattache chaque matière et chaque enseignant à une entrée existante quand le nom correspond, même approximativement (accents, abréviations, « Pr. », « M. ») ; sinon donne le nom pour une matière (elle sera créée) et signale l'enseignant manquant (l'admin devra fournir son téléphone pour proposer_creer_enseignant). Laisse date_debut et date_fin à null pour couvrir tout le semestre, sauf indication contraire.
4. Pour une liste d'étudiants : une proposition par étudiant avec nom, matricule, salle et formation ; ne fusionne ni n'omets personne.
5. Pour modifier ou supprimer des séances : consulte d'abord l'emploi du temps de la salle pour obtenir les identifiants exacts de séance ; une séance déjà tenue ne peut pas être touchée.
6. En cas d'ambiguïté (deux salles possibles, un enseignant inconnu, des horaires illisibles), pose une question précise plutôt que de deviner.
7. Ta réponse finale : ce que tu as proposé, ce qui manque ou t'a semblé douteux, en quelques lignes. Pas de formules de politesse inutiles.
TXT;
    }
}
