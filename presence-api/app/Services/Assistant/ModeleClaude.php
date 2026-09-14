<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Messages\Message;

/**
 * Implémentation Claude via le SDK officiel. Le prompt système et les
 * outils ne changent pas d'un tour à l'autre : ils sont marqués pour le
 * cache de préfixe, si bien que seuls les messages sont refacturés.
 */
class ModeleClaude implements Modele
{
    private ?Client $client = null;

    public function __construct(
        private readonly ?string $cle,
        private readonly string $modele,
    ) {}

    public function disponible(): bool
    {
        return (string) $this->cle !== '';
    }

    public function repondre(string $systeme, array $messages, array $outils): ReponseModele
    {
        $reponse = $this->client()->messages->create(
            model: $this->modele,
            maxTokens: 16000,
            system: [
                ['type' => 'text', 'text' => $systeme, 'cacheControl' => ['type' => 'ephemeral']],
            ],
            tools: $outils,
            thinking: ['type' => 'adaptive'],
            messages: $messages,
        );

        return $this->convertir($reponse);
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->cle);
    }

    private function convertir(Message $message): ReponseModele
    {
        $contenu = [];
        $appels = [];

        foreach ($message->content as $bloc) {
            // Renvoyé tel quel au tour suivant : les blocs de réflexion portent
            // une signature à préserver, les appels d'outils leur identifiant.
            $contenu[] = $bloc->__serialize();

            if ($bloc->type === 'tool_use') {
                $appels[] = ['id' => $bloc->id, 'name' => $bloc->name, 'input' => (array) $bloc->input];
            }
        }

        return new ReponseModele(
            contenu: $contenu,
            raisonArret: (string) ($message->stopReason ?? 'end_turn'),
            appelsOutils: $appels,
            jetonsEntree: (int) ($message->usage->inputTokens ?? 0),
            jetonsSortie: (int) ($message->usage->outputTokens ?? 0),
        );
    }
}
