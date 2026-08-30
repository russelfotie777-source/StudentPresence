<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seance_id', 'delegue_id', 'latitude', 'longitude'])]
class PositionSeance extends Model
{
    use HasFactory;

    // Table réelle "positions_seances" — la convention Eloquent par défaut
    // donnerait "position_seances".
    protected $table = 'positions_seances';

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'date_creation' => 'datetime',
        ];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function delegue(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegue_id');
    }
}
