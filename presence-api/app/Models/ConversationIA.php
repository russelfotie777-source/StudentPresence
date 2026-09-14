<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une conversation d'un admin avec l'assistant. `messages` est l'historique
 * au format de l'API du modèle (rôles user/assistant, blocs typés) ;
 * `actions` la liste des actions proposées, chacune avec son état
 * (en_attente, appliquee, echouee, ignoree) et son résultat ; `traitement`
 * l'état du message en cours d'analyse (asynchrone) ; `fichiers` les pièces
 * jointes conservées sur disque.
 */
#[Fillable(['admin_id', 'titre', 'messages', 'actions', 'traitement', 'fichiers'])]
class ConversationIA extends Model
{
    protected $table = 'conversations_ia';

    protected $attributes = ['messages' => '[]', 'actions' => '[]', 'fichiers' => '[]'];

    /** Dossier des pièces jointes de cette conversation sur le disque local. */
    public function dossierFichiers(): string
    {
        return "assistant/{$this->id}";
    }

    public function enTraitement(): bool
    {
        return ($this->traitement['statut'] ?? null) === 'en_cours';
    }

    /**
     * @param  array{fait?: int, total?: int}|null  $progression
     */
    public function avancer(string $etape, ?array $progression = null): void
    {
        $this->forceFill(['traitement' => [
            ...($this->traitement ?? []),
            'statut' => 'en_cours',
            'etape' => $etape,
            'progression' => $progression,
        ]])->save();
    }

    protected function casts(): array
    {
        return ['messages' => 'array', 'actions' => 'array', 'traitement' => 'array', 'fichiers' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
