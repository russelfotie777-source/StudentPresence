<?php

namespace App\Notifications;

use App\Models\Seance;
use App\Models\User;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Un étudiant a voulu pointer, mais la position de la salle n'est pas
 * encore envoyée : on fait signe au délégué. Une seule fois par séance,
 * de la part du premier qui a demandé — la classe entière derrière lui.
 */
class PositionAttendue extends Notification
{
    public function __construct(
        public readonly Seance $seance,
        public readonly User $etudiant,
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
        return 'La classe attend la position';
    }

    public function message(): string
    {
        $s = $this->seance;
        $cours = $s->courseTemplate?->matiere?->nom ?? 'la séance';

        return "{$this->etudiant->name} voudrait pointer pour {$cours} ({$s->salle->nom}). Envoyez la position de la salle pour ouvrir le pointage.";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'position_attendue',
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
            ->tag('seance-'.$this->seance->id)
            ->renotify(true)
            ->data(['url' => '/dashboard', 'seance_id' => $this->seance->id])
            ->options(['TTL' => 30 * 60, 'urgency' => 'high']);
    }
}
