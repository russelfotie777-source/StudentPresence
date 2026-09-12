<?php

namespace App\Models;

use Carbon\CarbonInterface;
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
            // Sérialisées en date pure : un horodatage ISO (minuit à Douala =
            // 23:00Z la veille) se retrouvait décalé d'un jour selon le fuseau
            // du navigateur qui l'affichait.
            'date_debut' => 'date:Y-m-d',
            'date_fin' => 'date:Y-m-d',
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
    /**
     * La semaine qui couvre strictement une date, sans repli sur la plus
     * proche : hors semestre, il n'y a tout simplement pas de semaine.
     */
    public static function couvrant(CarbonInterface|string $date): ?self
    {
        $jour = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return static::query()
            ->where('date_debut', '<=', $jour)
            ->where('date_fin', '>=', $jour)
            ->first();
    }

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
