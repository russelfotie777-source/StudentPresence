<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['numero', 'date_debut', 'date_fin'])]
class Semaine extends Model
{
    use HasFactory;

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

    /**
     * La semaine couvrant aujourd'hui, ou à défaut la plus proche (par date
     * de début) — reprend getCurrentWeek() de l'ancienne app
     * (dashboard.php/dashEtudiant.php), qui dupliquait cette logique dans
     * chaque fichier.
     */
    public static function current(): ?self
    {
        $today = now()->toDateString();

        return static::query()
            ->where('date_debut', '<=', $today)
            ->where('date_fin', '>=', $today)
            ->first()
            ?? static::query()
                ->orderByRaw('ABS(DATEDIFF(date_debut, ?))', [$today])
                ->first();
    }
}
