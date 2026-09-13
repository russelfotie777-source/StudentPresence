<?php

namespace App\Services;

use App\Enums\FormationType;
use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Assemble les données de la liste de présence hebdomadaire officielle
 * d'une salle, au format du département (en-tête bilingue, tableau des
 * étudiants avec une colonne par jour, tableau des séances de la semaine).
 */
class ListeHebdomadaire
{
    /** Mots vides ignorés pour former le sigle d'une filière. */
    private const MOTS_VIDES = ['de', 'des', 'du', 'et', 'la', 'le', 'les', 'l', 'd', 'en', 'a', 'à'];

    private const ROMAINS = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];

    /**
     * @return array<string, mixed>
     */
    public function pour(Salle $salle, Semaine $semaine, ?int $semestre = null, ?string $annee = null): array
    {
        $salle->loadMissing('filiere.niveau');
        $niveauChiffre = $this->chiffreDuNiveau($salle->filiere->niveau->nom);
        $option = $this->sigle($salle->filiere->nom);

        return [
            'etablissement' => config('presence.etablissement'),
            'logo' => $this->logoEnDataUri(),
            'option' => $option,
            'niveau_romain' => self::ROMAINS[$niveauChiffre] ?? (string) $niveauChiffre,
            'annee_academique' => $annee ?? $this->anneeAcademiqueCourante(),
            'semestre' => $semestre ?? $this->semestreCourant($niveauChiffre),
            'salle' => $salle->nom,
            // "GI2 – FI" : sigle + niveau + formation, comme sur la maquette.
            'groupe' => "{$option}{$niveauChiffre} – {$salle->formation->value}",
            'semaine_du' => $semaine->date_debut->format('d/m/Y'),
            'semaine_au' => $semaine->date_fin->format('d/m/Y'),
            'etudiants' => $this->etudiants($salle),
            'jours' => $this->seancesParJour($salle, $semaine),
            'contient_fm' => $salle->formation === FormationType::FI,
        ];
    }

    /**
     * Étudiants suivant les cours dans cette salle, par ordre alphabétique —
     * les FM (migrants FA → FI) y figurent dans une salle FI et sont
     * signalés, c'est le point que la liste papier doit faire ressortir.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function etudiants(Salle $salle): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Etudiant->value, UserRole::Delegue->value])
            ->where('salle_id', $salle->id)
            ->whereIn('formation', $salle->formationsAccueillies())
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'formation'])
            ->values()
            ->map(fn (User $u, int $i) => [
                'numero' => $i + 1,
                'matricule' => $u->phone,
                'nom' => Str::upper($u->name),
                'fm' => $u->formation === FormationType::FM,
            ]);
    }

    /**
     * Séances de la salle sur la semaine, groupées par jour (lundi → samedi),
     * pour préremplir le tableau du bas. Deux lignes par jour au minimum,
     * comme la maquette, même sans séance.
     *
     * @return array<string, array{label: string, seances: array<int, array<string, mixed>>}>
     */
    private function seancesParJour(Salle $salle, Semaine $semaine): array
    {
        $seances = Seance::query()
            ->with(['courseTemplate.matiere', 'enseignant'])
            ->where('salle_id', $salle->id)
            ->whereBetween('date_seance', [$semaine->date_debut->toDateString(), $semaine->date_fin->toDateString()])
            ->orderBy('heure_debut')
            ->get()
            ->groupBy(fn (Seance $s) => $s->jour->value);

        $jours = [];
        foreach ([Weekday::Lundi, Weekday::Mardi, Weekday::Mercredi, Weekday::Jeudi, Weekday::Vendredi, Weekday::Samedi] as $jour) {
            $lignes = ($seances[$jour->value] ?? collect())->map(fn (Seance $s) => [
                'ec' => trim(($s->courseTemplate?->matiere?->code ?? '').' '.($s->courseTemplate?->matiere?->nom ?? '')),
                'enseignant' => $s->enseignant?->name ?? '',
                'debut' => substr($s->heure_debut, 0, 5),
                'fin' => substr($s->heure_fin, 0, 5),
                'duree' => $this->duree($s->heure_debut, $s->heure_fin),
            ])->values()->all();

            $jours[$jour->value] = [
                'label' => Str::ucfirst(Str::lower($jour->value)),
                'seances' => array_pad($lignes, max(2, count($lignes)), null),
            ];
        }

        return $jours;
    }

    /** "Génie Informatique" → "GI", "Génie des Réseaux et Télécoms" → "GRT". */
    public function sigle(string $nom): string
    {
        $mots = preg_split("/[\s'’-]+/u", Str::ascii($nom)) ?: [];
        $initiales = collect($mots)
            ->filter(fn ($m) => $m !== '' && ! in_array(Str::lower($m), self::MOTS_VIDES, true))
            ->map(fn ($m) => Str::upper(Str::substr($m, 0, 1)));

        // Un nom déjà en sigle ("GRT") n'a pas à être réduit à sa lettre.
        if ($initiales->count() === 1 && Str::upper($nom) === $nom) {
            return $nom;
        }

        return $initiales->implode('');
    }

    /** "L2" → 2, "DUT2" → 2, "Niveau 3" → 3. */
    public function chiffreDuNiveau(string $nom): int
    {
        return preg_match('/(\d+)/', $nom, $m) ? (int) $m[1] : 1;
    }

    /** Septembre → janvier : année N-N+1 ; sinon N-1-N. */
    public function anneeAcademiqueCourante(): string
    {
        $annee = (int) now()->format('Y');

        return now()->month >= 9 ? "{$annee}-".($annee + 1) : ($annee - 1)."-{$annee}";
    }

    /**
     * Le niveau II est en semestres 3 et 4 : semestre impair de septembre à
     * janvier, pair ensuite.
     */
    public function semestreCourant(int $niveau): int
    {
        $premier = ($niveau - 1) * 2 + 1;
        $mois = now()->month;

        return ($mois >= 9 || $mois <= 1) ? $premier : $premier + 1;
    }

    private function duree(string $debut, string $fin): string
    {
        $minutes = (strtotime($fin) - strtotime($debut)) / 60;
        if ($minutes <= 0) {
            return '';
        }

        return sprintf('%dh%02d', intdiv((int) $minutes, 60), (int) $minutes % 60);
    }

    /**
     * En data URI plutôt qu'en chemin : dompdf refuse les fichiers locaux
     * hors de son chroot, et une image manquante donnerait un document sans
     * logo sans aucun message.
     */
    private function logoEnDataUri(): ?string
    {
        $chemin = config('presence.etablissement.logo');

        if (! $chemin || ! is_file($chemin)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($chemin));
    }
}
