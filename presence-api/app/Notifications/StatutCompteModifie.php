<?php

namespace App\Notifications;

use App\Enums\StatutCompte;
use Illuminate\Notifications\Notification;

/**
 * Prévient la personne qu'un admin a changé le statut de son compte. Stockée
 * en base (canal database) : l'app la lit à la prochaine ouverture, sans
 * dépendre d'une infrastructure push qui n'existe pas encore.
 */
class StatutCompteModifie extends Notification
{
    public function __construct(
        public readonly StatutCompte $statut,
        public readonly ?string $motif,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'statut_compte',
            'statut' => $this->statut->value,
            'titre' => match ($this->statut) {
                StatutCompte::Restreint => 'Votre compte a été restreint',
                StatutCompte::Bloque => 'Votre compte a été bloqué',
                StatutCompte::Actif => 'Votre compte a été rétabli',
            },
            'message' => match ($this->statut) {
                StatutCompte::Restreint => 'Vous pouvez consulter l\'application, mais le pointage de présence vous est refusé jusqu\'à nouvel ordre.',
                StatutCompte::Bloque => 'La connexion à l\'application vous est refusée.',
                StatutCompte::Actif => 'Vous pouvez de nouveau pointer votre présence normalement.',
            },
            'motif' => $this->motif,
        ];
    }
}
