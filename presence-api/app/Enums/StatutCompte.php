<?php

namespace App\Enums;

/**
 * Statut d'exploitation d'un compte, décidé par l'admin. Distinct de
 * ValidationStatus, qui ne concerne que l'entrée en fonction d'un
 * délégué/enseignant.
 */
enum StatutCompte: string
{
    case Actif = 'actif';

    /** Connexion permise, pointage refusé : la personne voit le motif. */
    case Restreint = 'restreint';

    /** Connexion refusée, sessions en cours révoquées. */
    case Bloque = 'bloque';
}
