<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\TraiterMessageIA;
use App\Models\ConversationIA;
use App\Services\Assistant\Assistant;
use App\Services\Assistant\ExecuteurActions;
use App\Services\Assistant\PiecesJointes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * L'assistant IA du back-office : des conversations par admin, un message
 * à la fois, et l'application explicite des actions proposées. Le modèle
 * ne touche jamais aux données sans ce dernier clic.
 */
class AssistantController extends Controller
{
    public function etat(Assistant $assistant): JsonResponse
    {
        return response()->json([
            'disponible' => $assistant->disponible(),
            'modele' => config('services.anthropic.model'),
            'types_fichiers' => array_keys(PiecesJointes::TYPES),
            'extensions' => PiecesJointes::EXTENSIONS,
            'taille_max_mo' => (int) config('services.anthropic.taille_max_fichier_mo', 25),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = ConversationIA::query()
            ->where('admin_id', $request->user()->id)
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (ConversationIA $c) => [
                'id' => $c->id,
                'titre' => $c->titre,
                'mise_a_jour' => $c->updated_at?->toIso8601String(),
                'actions_en_attente' => collect($c->actions)->where('statut', 'en_attente')->count(),
            ]);

        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $conversation = ConversationIA::create(['admin_id' => $request->user()->id]);

        return response()->json($this->presenter($conversation), 201);
    }

    public function show(Request $request, ConversationIA $conversation): JsonResponse
    {
        $this->assertProprietaire($request, $conversation);

        return response()->json($this->presenter($conversation));
    }

    public function destroy(Request $request, ConversationIA $conversation, PiecesJointes $pieces): JsonResponse
    {
        $this->assertProprietaire($request, $conversation);
        $pieces->supprimerTout($conversation);
        $conversation->delete();

        return response()->json(null, 204);
    }

    /**
     * Un message de l'admin, avec éventuellement des fichiers (PDF, images,
     * tableurs, texte). Le traitement part en file d'attente : l'interface
     * suit `traitement` sur la conversation jusqu'à « termine ».
     */
    public function envoyer(Request $request, ConversationIA $conversation, Assistant $assistant, PiecesJointes $pieces): JsonResponse
    {
        $this->assertProprietaire($request, $conversation);

        if (! $assistant->disponible()) {
            return response()->json(['message' => "L'assistant n'est pas configuré : renseignez ANTHROPIC_API_KEY côté serveur."], 503);
        }

        if ($conversation->enTraitement()) {
            return response()->json(['message' => "L'assistant traite encore le message précédent."], 409);
        }

        $tailleMaxKo = (int) config('services.anthropic.taille_max_fichier_mo', 25) * 1024;

        $data = $request->validate([
            'texte' => ['nullable', 'string', 'max:20000', 'required_without:fichiers'],
            'fichiers' => ['sometimes', 'array', 'max:5'],
            'fichiers.*' => ['file', 'extensions:'.implode(',', PiecesJointes::EXTENSIONS), "max:{$tailleMaxKo}"],
        ], [
            'texte.required_without' => 'Écrivez un message ou joignez un fichier.',
            'fichiers.*.extensions' => 'Formats acceptés : PDF, image, tableur (xlsx, xls, csv) ou texte.',
            'fichiers.*.max' => 'Un fichier ne peut pas dépasser '.($tailleMaxKo / 1024).' Mo.',
        ]);

        $ids = [];
        foreach ($request->file('fichiers', []) as $fichier) {
            $ids[] = $pieces->enregistrer($conversation, $fichier)['id'];
        }

        $conversation->forceFill(['traitement' => [
            'statut' => 'en_cours',
            'etape' => "En file d'attente",
            'progression' => null,
            'erreur' => null,
            'demarre_le' => now()->toIso8601String(),
        ]])->save();

        TraiterMessageIA::dispatch($conversation->id, (string) ($data['texte'] ?? ''), $ids);

        return response()->json(['conversation' => $this->presenter($conversation->fresh())], 202);
    }

    /**
     * Identifiants des comptes créés par un import (matricule, mot de passe
     * initial), en CSV pour Excel : c'est ce que l'admin distribue.
     */
    public function identifiants(Request $request, ConversationIA $conversation, string $actionId): StreamedResponse|JsonResponse
    {
        $this->assertProprietaire($request, $conversation);

        $action = collect($conversation->actions ?? [])->firstWhere('id', $actionId);
        $identifiants = $action['resultat']['details']['identifiants'] ?? null;

        if (! $action || ! is_array($identifiants)) {
            return response()->json(['message' => 'Aucun identifiant pour cette action.'], 404);
        }

        $nom = 'identifiants_'.Str::slug($action['resume'] ?? 'import').'.csv';

        return response()->streamDownload(function () use ($identifiants) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF"); // BOM : Excel ouvre l'UTF-8 correctement
            fputcsv($sortie, ['Nom et prénoms', 'Matricule (identifiant)', 'Mot de passe initial', 'Salle'], ';');
            foreach ($identifiants as $i) {
                fputcsv($sortie, [$i['nom'] ?? '', $i['matricule'] ?? '', $i['mot_de_passe'] ?? '', $i['salle'] ?? ''], ';');
            }
            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Applique les actions cochées, dans l'ordre où elles ont été proposées
     * (une matière créée par la première sert à la suivante). Les autres
     * actions en attente restent en attente.
     */
    public function appliquer(Request $request, ConversationIA $conversation, ExecuteurActions $executeur): JsonResponse
    {
        $this->assertProprietaire($request, $conversation);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        $actions = $conversation->actions ?? [];
        $appliquees = 0;
        $echouees = 0;

        foreach ($actions as &$action) {
            if (! in_array($action['id'], $data['ids'], true) || $action['statut'] !== 'en_attente') {
                continue;
            }

            $resultat = $executeur->executer($action);
            $action['statut'] = $resultat['ok'] ? 'appliquee' : 'echouee';
            $action['resultat'] = $resultat;
            $action['appliquee_le'] = now()->toIso8601String();
            $resultat['ok'] ? $appliquees++ : $echouees++;
        }
        unset($action);

        // Le modèle doit savoir ce qui a réellement été fait pour la suite.
        $messages = $conversation->messages ?? [];
        $bilan = collect($actions)->whereIn('id', $data['ids'])->map(fn ($a) => sprintf(
            '- %s → %s%s',
            $a['resume'],
            $a['statut'] === 'appliquee' ? 'appliquée' : ($a['statut'] === 'echouee' ? 'ÉCHEC' : $a['statut']),
            $a['resultat']['message'] ?? null ? ' ('.$a['resultat']['message'].')' : '',
        ))->implode("\n");
        $messages[] = ['role' => 'user', 'content' => [['type' => 'text', 'text' => "[Système] Actions traitées par l'admin :\n{$bilan}"]]];
        $messages[] = ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Bien noté.']]];

        $conversation->fill(['actions' => $actions, 'messages' => $messages])->save();

        return response()->json([
            'appliquees' => $appliquees,
            'echouees' => $echouees,
            'actions' => array_map([$this, 'allegerAction'], $actions),
        ]);
    }

    /** Écarte des actions sans les appliquer. */
    public function ignorer(Request $request, ConversationIA $conversation): JsonResponse
    {
        $this->assertProprietaire($request, $conversation);

        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['string']]);

        $actions = collect($conversation->actions ?? [])->map(function ($a) use ($data) {
            if (in_array($a['id'], $data['ids'], true) && $a['statut'] === 'en_attente') {
                $a['statut'] = 'ignoree';
            }

            return $a;
        })->values()->all();

        $conversation->fill(['actions' => $actions])->save();

        return response()->json(['actions' => array_map([$this, 'allegerAction'], $actions)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presenter(ConversationIA $conversation): array
    {
        $transcription = [];

        foreach ($conversation->messages ?? [] as $message) {
            $contenu = $message['content'];
            if (is_string($contenu)) {
                $transcription[] = ['role' => $message['role'], 'texte' => $contenu];

                continue;
            }

            $textes = collect($contenu)
                ->filter(fn ($b) => ($b['type'] ?? null) === 'text')
                ->pluck('text')
                // Les bilans d'application sont pour le modèle, l'admin les a déjà vus.
                ->reject(fn ($t) => str_starts_with($t, '[Système]'))
                ->filter(fn ($t) => trim($t) !== '' && $t !== 'Bien noté.')
                ->values();

            if ($textes->isNotEmpty()) {
                $transcription[] = ['role' => $message['role'], 'texte' => $textes->implode("\n\n")];
            }
        }

        return [
            'id' => $conversation->id,
            'titre' => $conversation->titre,
            'messages' => $transcription,
            'actions' => array_map([$this, 'allegerAction'], $conversation->actions ?? []),
            'traitement' => $conversation->traitement,
            'fichiers' => array_map(fn ($f) => [
                'id' => $f['id'], 'nom' => $f['nom'], 'genre' => $f['genre'], 'taille' => $f['taille'],
                'pages' => $f['pages'] ?? null, 'feuilles' => array_column($f['feuilles'] ?? [], 'nom'),
            ], $conversation->fichiers ?? []),
            'mise_a_jour' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Un import de 2 000 lignes ne transite pas dans chaque réponse : l'admin
     * voit le total, l'aperçu et les anomalies ; les identifiants créés
     * partent par l'export CSV.
     *
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function allegerAction(array $action): array
    {
        if (in_array($action['type'], ['importer_etudiants', 'importer_cours'], true)) {
            $p = $action['parametres'];
            unset($p['etudiants'], $p['cours']);
            $action['parametres'] = $p;

            if (isset($action['resultat']['details']['identifiants'])) {
                $action['resultat']['details']['identifiants_count'] = count($action['resultat']['details']['identifiants']);
                unset($action['resultat']['details']['identifiants']);
            }
        }

        return $action;
    }

    private function assertProprietaire(Request $request, ConversationIA $conversation): void
    {
        abort_unless($conversation->admin_id === $request->user()->id, 404);
    }
}
