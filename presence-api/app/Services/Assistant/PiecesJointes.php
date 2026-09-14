<?php

namespace App\Services\Assistant;

use App\Models\ConversationIA;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Les fichiers que l'admin joint à une conversation : conservés sur le
 * disque privé le temps de la conversation, parce que leur traitement est
 * asynchrone et qu'un tableur est relu au moment d'appliquer un import.
 */
class PiecesJointes
{
    public const DISQUE = 'local';

    /** Au-delà, un PDF n'est plus lu d'un bloc par le modèle : il passe par l'extraction par tranches. */
    public const PAGES_LECTURE_DIRECTE = 15;

    public const TYPES = [
        'application/pdf' => 'pdf',
        'image/png' => 'image',
        'image/jpeg' => 'image',
        'image/webp' => 'image',
        'image/gif' => 'image',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'tableur',
        'application/vnd.ms-excel' => 'tableur',
        'text/csv' => 'tableur',
        'text/plain' => 'texte',
    ];

    public const EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'xlsx', 'xls', 'csv', 'txt'];

    public function __construct(
        private DecoupeurPDF $decoupeur,
        private LecteurTableur $tableur,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function enregistrer(ConversationIA $conversation, UploadedFile $fichier): array
    {
        $extension = Str::lower($fichier->getClientOriginalExtension() ?: $fichier->guessExtension() ?: 'bin');
        $id = (string) Str::uuid();
        $chemin = "{$conversation->dossierFichiers()}/{$id}.{$extension}";
        Storage::disk(self::DISQUE)->putFileAs($conversation->dossierFichiers(), $fichier, "{$id}.{$extension}");

        $genre = $this->genre($fichier->getMimeType() ?? '', $extension);
        $descripteur = [
            'id' => $id,
            'nom' => $fichier->getClientOriginalName(),
            'type' => $fichier->getMimeType(),
            'genre' => $genre,
            'extension' => $extension,
            'taille' => $fichier->getSize(),
            'chemin' => $chemin,
            'ajoute_le' => now()->toIso8601String(),
        ];

        if ($genre === 'pdf') {
            try {
                $descripteur['pages'] = $this->decoupeur->nombreDePages($this->cheminAbsolu($descripteur));
            } catch (\Throwable) {
                $descripteur['pages'] = null;
            }
        }

        if ($genre === 'tableur') {
            try {
                $descripteur['feuilles'] = $this->tableur->feuilles($this->cheminAbsolu($descripteur));
            } catch (\Throwable $e) {
                $descripteur['erreur'] = 'Tableur illisible : '.$e->getMessage();
            }
        }

        $conversation->fill(['fichiers' => [...($conversation->fichiers ?? []), $descripteur]])->save();

        return $descripteur;
    }

    /**
     * @param  array<string, mixed>  $fichier
     */
    public function cheminAbsolu(array $fichier): string
    {
        return Storage::disk(self::DISQUE)->path($fichier['chemin']);
    }

    /**
     * @param  array<string, mixed>  $fichier
     */
    public function contenuBase64(array $fichier): string
    {
        return base64_encode((string) file_get_contents($this->cheminAbsolu($fichier)));
    }

    public function supprimerTout(ConversationIA $conversation): void
    {
        Storage::disk(self::DISQUE)->deleteDirectory($conversation->dossierFichiers());
    }

    /**
     * Retrouve un fichier de la conversation par son numéro (1-based, tel que
     * présenté au modèle) ou son identifiant.
     *
     * @return array<string, mixed>|null
     */
    public function trouver(ConversationIA $conversation, int|string $reference): ?array
    {
        $fichiers = array_values($conversation->fichiers ?? []);

        if (is_int($reference) || ctype_digit((string) $reference)) {
            return $fichiers[(int) $reference - 1] ?? null;
        }

        return collect($fichiers)->firstWhere('id', $reference);
    }

    /**
     * Blocs de contenu à joindre au message du modèle pour ce fichier : le
     * fichier lui-même quand il peut être lu d'un bloc, sinon une description
     * qui renvoie vers les outils d'extraction.
     *
     * @param  array<string, mixed>  $fichier
     * @return list<array<string, mixed>>
     */
    public function blocs(array $fichier, int $numero): array
    {
        $entete = "Fichier n°{$numero} : « {$fichier['nom']} »";

        switch ($fichier['genre']) {
            case 'image':
                if (($fichier['taille'] ?? 0) > 5 * 1024 * 1024) {
                    return [['type' => 'text', 'text' => "{$entete} — image de plus de 5 Mo, non lisible par le modèle. Demandez une version plus légère."]];
                }

                return [
                    ['type' => 'text', 'text' => $entete],
                    ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $fichier['type'], 'data' => $this->contenuBase64($fichier)]],
                ];

            case 'pdf':
                $pages = $fichier['pages'] ?? null;
                if ($pages !== null && $pages <= self::PAGES_LECTURE_DIRECTE && ($fichier['taille'] ?? 0) <= 20 * 1024 * 1024) {
                    return [
                        ['type' => 'text', 'text' => "{$entete} ({$pages} page".($pages > 1 ? 's' : '').')'],
                        ['type' => 'document', 'title' => $fichier['nom'], 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $this->contenuBase64($fichier)]],
                    ];
                }

                return [['type' => 'text', 'text' => sprintf(
                    "%s — PDF de %s page(s), trop long pour être lu d'un bloc. Utilise extraire_etudiants_pdf (liste d'étudiants) ou extraire_cours_pdf (emploi du temps) avec fichier = %d : le serveur le lira par tranches et enregistrera une proposition d'import.",
                    $entete, $pages ?? '?', $numero,
                )]];

            case 'tableur':
                if (isset($fichier['erreur'])) {
                    return [['type' => 'text', 'text' => "{$entete} — {$fichier['erreur']}"]];
                }

                return [['type' => 'text', 'text' => $this->tableur->apercu($this->cheminAbsolu($fichier), $fichier['nom'])
                    ."\n\nPour importer ces lignes, utilise proposer_import_etudiants avec fichier = {$numero} et le nom de la feuille : le serveur lira toutes les lignes lui-même, ne les recopie pas."]];

            case 'texte':
                $texte = (string) file_get_contents($this->cheminAbsolu($fichier));

                return [['type' => 'text', 'text' => "{$entete} :\n".Str::limit($texte, 200_000, "\n[…tronqué…]")]];
        }

        return [['type' => 'text', 'text' => "{$entete} — type non pris en charge."]];
    }

    private function genre(string $mime, string $extension): string
    {
        return self::TYPES[$mime] ?? match ($extension) {
            'pdf' => 'pdf',
            'png', 'jpg', 'jpeg', 'webp', 'gif' => 'image',
            'xlsx', 'xls', 'csv' => 'tableur',
            'txt' => 'texte',
            default => 'inconnu',
        };
    }
}
