<?php

namespace App\Services\Assistant;

/**
 * Frontière avec le fournisseur du modèle : une seule méthode, un tour de
 * conversation. La vraie implémentation parle à l'API Claude ; les tests
 * la remplacent par un scénario écrit à l'avance.
 */
interface Modele
{
    /**
     * @param  list<array<string, mixed>>  $messages  historique au format API
     * @param  list<array<string, mixed>>  $outils  définitions d'outils
     */
    public function repondre(string $systeme, array $messages, array $outils): ReponseModele;

    public function disponible(): bool;
}
