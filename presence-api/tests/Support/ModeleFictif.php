<?php

namespace Tests\Support;

use App\Services\Assistant\Modele;
use App\Services\Assistant\ReponseModele;

/**
 * Un modèle qui joue un scénario écrit à l'avance : chaque appel rend la
 * réponse suivante de la file et garde en mémoire ce qu'il a reçu, pour
 * que les tests vérifient aussi ce que la boucle lui a renvoyé.
 */
class ModeleFictif implements Modele
{
    /** @var list<array{systeme: string, messages: list<array<string, mixed>>, outils: list<array<string, mixed>>}> */
    public array $appels = [];

    /** @param  list<ReponseModele>  $scenario */
    public function __construct(private array $scenario, private bool $disponible = true) {}

    public function disponible(): bool
    {
        return $this->disponible;
    }

    public function repondre(string $systeme, array $messages, array $outils): ReponseModele
    {
        $this->appels[] = ['systeme' => $systeme, 'messages' => $messages, 'outils' => $outils];

        return array_shift($this->scenario)
            ?? new ReponseModele([['type' => 'text', 'text' => '(scénario épuisé)']], 'end_turn', []);
    }

    public static function texte(string $texte): ReponseModele
    {
        return new ReponseModele([['type' => 'text', 'text' => $texte]], 'end_turn', [], 100, 20);
    }

    /**
     * @param  list<array{name: string, input: array<string, mixed>}>  $appels
     */
    public static function outils(array $appels, string $texte = ''): ReponseModele
    {
        $contenu = $texte !== '' ? [['type' => 'text', 'text' => $texte]] : [];
        $liste = [];
        foreach ($appels as $i => $appel) {
            $id = 'toolu_'.uniqid().$i;
            $contenu[] = ['type' => 'tool_use', 'id' => $id, 'name' => $appel['name'], 'input' => $appel['input']];
            $liste[] = ['id' => $id, 'name' => $appel['name'], 'input' => $appel['input']];
        }

        return new ReponseModele($contenu, 'tool_use', $liste, 100, 50);
    }
}
