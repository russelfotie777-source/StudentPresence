<?php

namespace App\Notifications;

use App\Models\Seance;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Rappel lié à une séance : au délégué d'envoyer la position, aux étudiants
 * que le pointage est ouvert, puis qu'il va fermer. Toujours stocké en base
 * (la cloche de l'app) ; poussé sur le téléphone en plus dès que des clés
 * VAPID sont configurées et que la personne a accepté les notifications.
 */
class RappelPointage extends Notification
{
    public const DELEGUE = 'delegue';

    public const OUVERTURE = 'ouverture';

    public const DERNIERE_CHANCE = 'derniere_chance';

    public function __construct(
        public readonly Seance $seance,
        public readonly string $sousType,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $canaux = ['database'];

        if (config('webpush.vapid.public_key') && config('webpush.vapid.private_key')) {
            $canaux[] = WebPushChannel::class;
        }

        return $canaux;
    }

    public function titre(): string
    {
        return match ($this->sousType) {
            self::DELEGUE => 'Séance dans quelques minutes : envoyez la position',
            self::OUVERTURE => 'Le pointage est ouvert',
            default => 'Dernière chance pour pointer',
        };
    }

    public function message(): string
    {
        $s = $this->seance;
        $cours = $s->courseTemplate?->matiere?->nom ?? 'la séance';
        $debut = substr($s->heure_debut, 0, 5);
        $fin = substr($s->heure_fin, 0, 5);
        $fermeture = $s->finPrevue()->addMinutes(15)->format('H:i');

        return match ($this->sousType) {
            self::DELEGUE => "{$cours} commence à {$debut} en {$s->salle->nom}. Envoyez la position de la salle pour ouvrir le pointage à la classe.",
            self::OUVERTURE => "{$cours} ({$debut}–{$fin}, {$s->salle->nom}) : pointez votre présence depuis la salle avant {$fermeture}.",
            default => "Vous n'avez pas encore pointé pour {$cours} ({$debut}–{$fin}). Le pointage ferme à {$fermeture}.",
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rappel_pointage',
            'sous_type' => $this->sousType,
            'seance_id' => $this->seance->id,
            'titre' => $this->titre(),
            'message' => $this->message(),
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->titre())
            ->body($this->message())
            ->icon('/iut-douala.png')
            ->badge('/iut-douala.png')
            // Un seul rappel visible par séance : le suivant remplace le précédent.
            ->tag('seance-'.$this->seance->id)
            ->renotify(true)
            ->data(['url' => '/dashboard', 'seance_id' => $this->seance->id])
            // Inutile de livrer un rappel après la fermeture du pointage.
            ->options(['TTL' => 30 * 60, 'urgency' => 'high']);
    }
}
