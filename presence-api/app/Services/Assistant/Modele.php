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

    /**
     * Extraction structurée d'un document : le modèle lit le PDF joint et
     * répond en JSON conforme au schéma, sans outils. Renvoie le JSON
     * décodé, ou null si la réponse a débordé (max_tokens) — l'appelant
     * réduit alors la tranche de pages.
     *
     * @param  array<string, mixed>  $schema  JSON Schema de la réponse attendue
     * @return array<string, mixed>|null
     */
    public function structurer(string $consigne, string $pdfBase64, array $schema): ?array;

    public function disponible(): bool;
}
