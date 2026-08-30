<?php

namespace App\Models;

use App\Enums\PresenceState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seance_id', 'etudiant_id', 'etat', 'date_marquage'])]
class PresenceEtudiant extends Model
{
    protected function casts(): array
    {
        return [
            'etat' => PresenceState::class,
            'date_marquage' => 'datetime',
        ];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etudiant_id');
    }
}
