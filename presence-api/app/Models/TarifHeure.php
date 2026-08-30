<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['niveau_id', 'tarif_heure'])]
class TarifHeure extends Model
{
    protected function casts(): array
    {
        return ['tarif_heure' => 'decimal:2'];
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }
}
