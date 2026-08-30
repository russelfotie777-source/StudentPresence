<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['numero', 'date_debut', 'date_fin'])]
class Semaine extends Model
{
    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }
}
