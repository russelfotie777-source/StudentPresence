<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une conversation d'un admin avec l'assistant. `messages` est l'historique
 * au format de l'API du modèle (rôles user/assistant, blocs typés) ;
 * `actions` la liste des actions proposées, chacune avec son état
 * (en_attente, appliquee, echouee, ignoree) et son résultat.
 */
#[Fillable(['admin_id', 'titre', 'messages', 'actions'])]
class ConversationIA extends Model
{
    protected $table = 'conversations_ia';

    protected $attributes = ['messages' => '[]', 'actions' => '[]'];

    protected function casts(): array
    {
        return ['messages' => 'array', 'actions' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
