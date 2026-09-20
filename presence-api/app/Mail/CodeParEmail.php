<?php

namespace App\Mail;

use App\Models\CodeEmail;
use App\Models\User;
use App\Services\CodesEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodeParEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $usage,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->usage === CodeEmail::REINITIALISATION
                ? "Votre code pour changer de mot de passe : {$this->code}"
                : "Votre code de vérification : {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.code', with: [
            'prenom' => explode(' ', trim($this->user->name))[0],
            'reinitialisation' => $this->usage === CodeEmail::REINITIALISATION,
            'validite' => CodesEmail::VALIDITE_MINUTES,
        ]);
    }
}
