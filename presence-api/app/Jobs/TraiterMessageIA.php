<?php

namespace App\Jobs;

use App\Models\ConversationIA;
use App\Services\Assistant\Assistant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Traite un message de l'admin en arrière-plan : la boucle avec le modèle
 * peut lire un PDF de deux cents pages tranche par tranche, ce qu'aucune
 * requête HTTP ne peut attendre. L'interface suit `traitement` sur la
 * conversation.
 */
class TraiterMessageIA implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  list<string>  $fichierIds  pièces jointes de ce message
     */
    public function __construct(
        public int $conversationId,
        public string $texte,
        public array $fichierIds,
    ) {
        $this->onQueue('assistant');
    }

    public function handle(Assistant $assistant): void
    {
        $conversation = ConversationIA::find($this->conversationId);
        if (! $conversation) {
            return;
        }

        $fichiers = collect($conversation->fichiers ?? [])->whereIn('id', $this->fichierIds)->values()->all();

        try {
            $resultat = $assistant->repondre(
                $conversation,
                $this->texte,
                $fichiers,
                fn (string $etape, ?array $progression) => $conversation->avancer($etape, $progression),
            );

            $conversation->forceFill(['traitement' => [
                'statut' => 'termine',
                'etape' => null,
                'progression' => null,
                'erreur' => null,
                'termine_le' => now()->toIso8601String(),
                'nouvelles_actions' => $resultat['nouvelles'],
                'jetons' => $resultat['jetons'],
            ]])->save();
        } catch (Throwable $e) {
            report($e);
            $conversation->refresh()->forceFill(['traitement' => [
                'statut' => 'erreur',
                'etape' => null,
                'progression' => null,
                'erreur' => $this->messageLisible($e),
                'termine_le' => now()->toIso8601String(),
            ]])->save();
        }
    }

    public function failed(?Throwable $e): void
    {
        ConversationIA::find($this->conversationId)?->forceFill(['traitement' => [
            'statut' => 'erreur',
            'etape' => null,
            'progression' => null,
            'erreur' => $e ? $this->messageLisible($e) : 'Traitement interrompu.',
            'termine_le' => now()->toIso8601String(),
        ]])->save();
    }

    private function messageLisible(Throwable $e): string
    {
        $classe = class_basename($e);

        return match (true) {
            str_contains($classe, 'RateLimit') => 'Le service du modèle est saturé pour le moment. Réessayez dans une minute.',
            str_contains($classe, 'Authentication') => 'La clé API du modèle est refusée : vérifiez ANTHROPIC_API_KEY.',
            str_contains($classe, 'Timeout'), str_contains($classe, 'Connection') => 'Le service du modèle ne répond pas. Réessayez.',
            default => "L'assistant a rencontré une erreur : ".$e->getMessage(),
        };
    }
}
