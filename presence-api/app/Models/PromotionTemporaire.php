<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['etudiant_id', 'promoteur_id', 'date_debut', 'date_fin', 'duree_minutes'])]
class PromotionTemporaire extends Model
{
    // Table réelle "promotions_temporaires" — la convention Eloquent par
    // défaut donnerait "promotion_temporaires".
    protected $table = 'promotions_temporaires';

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
        ];
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etudiant_id');
    }

    public function promoteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoteur_id');
    }

    public function estActive(): bool
    {
        return $this->date_fin->isFuture();
    }
}
