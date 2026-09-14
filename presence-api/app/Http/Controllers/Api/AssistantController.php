<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConversationIA;
use App\Services\Assistant\Assistant;
use App\Services\Assistant\ExecuteurActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * L'assistant IA du back-office : des conversations par admin, un message
 * à la fois, et l'application explicite des actions proposées. Le modèle
 * ne touche jamais aux données sans ce dernier clic.
 */
class AssistantController extends Controller
{
    /** Taille maximale d'une pièce jointe décodée (octets). */
    private const TAILLE_MAX_FICHIER = 8 * 1024 * 1024;

    public function etat(Assistant $assistant): JsonResponse
    {
        return response()->json([
            'disponible' => $assistant->disponible(),
            'modele' => config('services.anthropic.model'),
            'types_fichiers' => array_keys(Assistant::TYPES_FICHIERS),
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

    public function destroy(Request $request, ConversationIA $conversation): JsonResponse
    {
        $this->assertProprietaire($request, $conversation);
        $conversation->delete();

        return response()->json(null, 204);
    }

    /**
     * Un message de l'admin, avec éventuellement des fichiers (PDF, images)
     * encodés en base64. La boucle avec le modèle peut durer : on laisse à
     * PHP le temps de la finir.
     */
    public function envoyer(Request $request, ConversationIA $conversation, Assistant $assistant): JsonResponse
    {
        $this->assertProprietaire($request, $conversation);

        if (! $assistant->disponible()) {
            return response()->json(['message' => "L'assistant n'est pas configuré : renseignez ANTHROPIC_API_KEY côté serveur."], 503);
        }

        $data = $request->validate([
            'texte' => ['nullable', 'string', 'max:20000', 'required_without:fichiers'],
            'fichiers' => ['sometimes', 'array', 'max:3'],
            'fichiers.*.nom' => ['required', 'string', 'max:200'],
            'fichiers.*.type' => ['required', Rule::in(array_keys(Assistant::TYPES_FICHIERS))],
            'fichiers.*.base64' => ['required', 'string'],
        ], [
            'texte.required_without' => 'Écrivez un message ou joignez un fichier.',
        ]);

        foreach ($data['fichiers'] ?? [] as $i => $fichier) {
            $decode = base64_decode($fichier['base64'], true);
            if ($decode === false || strlen($decode) === 0) {
                return response()->json(['message' => "Le fichier « {$fichier['nom']} » est illisible."], 422);
            }
            if (strlen($decode) > self::TAILLE_MAX_FICHIER) {
                return response()->json(['message' => "Le fichier « {$fichier['nom']} » dépasse 8 Mo."], 422);
            }
        }

        set_time_limit(300);

        $resultat = $assistant->repondre($conversation, (string) ($data['texte'] ?? ''), $data['fichiers'] ?? []);

        return response()->json([
            'reponse' => $resultat['texte'],
            'actions' => $resultat['actions'],
            'nouvelles' => $resultat['nouvelles'],
            'conversation' => $this->presenter($conversation->fresh()),
            'jetons' => $resultat['jetons'],
        ]);
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
            'actions' => $actions,
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

        return response()->json(['actions' => $actions]);
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
            'actions' => $conversation->actions ?? [],
            'mise_a_jour' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    private function assertProprietaire(Request $request, ConversationIA $conversation): void
    {
        abort_unless($conversation->admin_id === $request->user()->id, 404);
    }
}
