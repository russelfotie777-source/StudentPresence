<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Compte et découpe les pages d'un PDF avec Ghostscript. Un PDF de 200
 * pages ne tient pas dans une seule requête au modèle : on l'envoie par
 * tranches, et on réduit la tranche quand la réponse déborde.
 */
class DecoupeurPDF
{
    public function disponible(): bool
    {
        return Process::run('gs --version')->successful();
    }

    public function nombreDePages(string $chemin): int
    {
        $resultat = Process::run([
            'gs', '-q', '-dNODISPLAY', '-dNOSAFER', '-c',
            '('.addcslashes($chemin, '()\\').') (r) file runpdfbegin pdfpagecount = quit',
        ]);

        if (! $resultat->successful() || ! is_numeric(trim($resultat->output()))) {
            throw new RuntimeException('Impossible de compter les pages du PDF : '.trim($resultat->errorOutput() ?: $resultat->output()));
        }

        return (int) trim($resultat->output());
    }

    /**
     * Extrait les pages [$de, $a] (1-based, incluses) dans un nouveau PDF et
     * renvoie son contenu.
     */
    public function pages(string $chemin, int $de, int $a): string
    {
        $sortie = tempnam(sys_get_temp_dir(), 'tranche').'.pdf';

        try {
            $resultat = Process::timeout(120)->run([
                'gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite',
                "-dFirstPage={$de}", "-dLastPage={$a}", "-sOutputFile={$sortie}", $chemin,
            ]);

            if (! $resultat->successful() || ! is_file($sortie)) {
                throw new RuntimeException("Impossible d'extraire les pages {$de}–{$a} : ".trim($resultat->errorOutput()));
            }

            return (string) file_get_contents($sortie);
        } finally {
            @unlink($sortie);
        }
    }
}
