<?php

namespace App\Services\Assistant;

/**
 * Ce que le modèle a répondu à un tour, réduit à ce dont la boucle a
 * besoin : les blocs de contenu tels qu'ils doivent être renvoyés dans
 * l'historique, la raison d'arrêt, et les appels d'outils à exécuter.
 *
 * @param  list<array<string, mixed>>  $contenu  blocs au format API (camelCase)
 * @param  list<array{id: string, name: string, input: array<string, mixed>}>  $appelsOutils
 */
final readonly class ReponseModele
{
    public function __construct(
        public array $contenu,
        public string $raisonArret,
        public array $appelsOutils,
        public int $jetonsEntree = 0,
        public int $jetonsSortie = 0,
    ) {}

    public function texte(): string
    {
        return collect($this->contenu)
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n\n");
    }
}
