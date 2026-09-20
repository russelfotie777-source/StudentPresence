<?php

namespace App\Services;

use App\Mail\CodeParEmail;
use App\Models\CodeEmail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Codes à six chiffres envoyés par e-mail : confirmer une adresse, ou
 * remplacer un mot de passe oublié. Un code vit trente minutes, tolère
 * cinq essais, et on n'en renvoie pas un avant une minute — assez pour
 * l'étudiant qui cherche dans ses spams, trop peu pour qui devine.
 */
class CodesEmail
{
    public const VALIDITE_MINUTES = 30;

    public const ESSAIS_MAX = 5;

    public const DELAI_RENVOI_SECONDES = 60;

    /**
     * Génère, enregistre et envoie un code. L'envoi est synchrone : si le
     * serveur de mail ne répond pas, l'utilisateur le sait tout de suite
     * plutôt que d'attendre un e-mail qui ne viendra pas.
     */
    public function envoyer(User $user, string $usage, string $email): CodeEmail
    {
        $precedent = $user->codesEmail()->where('usage', $usage)->first();
        if ($precedent && $precedent->created_at->gt(now()->subSeconds(self::DELAI_RENVOI_SECONDES))) {
            $attente = self::DELAI_RENVOI_SECONDES - (int) $precedent->created_at->diffInSeconds(now());
            throw ValidationException::withMessages(['code' => ["Un code vient d'être envoyé à {$precedent->email}. Vous pourrez en redemander un dans {$attente} s."]]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            Mail::to($email)->send(new CodeParEmail($user, $usage, $code));
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['email' => ["L'e-mail n'a pas pu être envoyé. Vérifiez l'adresse, puis réessayez dans un instant."]]);
        }

        $precedent?->delete();

        return $user->codesEmail()->create([
            'usage' => $usage,
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expire_le' => now()->addMinutes(self::VALIDITE_MINUTES),
        ]);
    }

    /**
     * Confirme un code et rend l'adresse à laquelle il a été envoyé. Faux
     * cinq fois, expiré ou jamais demandé : il faut en redemander un.
     */
    public function confirmer(User $user, string $usage, string $code): string
    {
        $enregistre = $user->codesEmail()->where('usage', $usage)->first();

        if (! $enregistre) {
            throw ValidationException::withMessages(['code' => ['Aucun code en attente : demandez-en un nouveau.']]);
        }
        if ($enregistre->expire()) {
            $enregistre->delete();
            throw ValidationException::withMessages(['code' => ['Ce code a expiré : demandez-en un nouveau.']]);
        }
        if (! Hash::check(trim($code), $enregistre->code_hash)) {
            $enregistre->increment('tentatives');
            if ($enregistre->tentatives >= self::ESSAIS_MAX) {
                $enregistre->delete();
                throw ValidationException::withMessages(['code' => ['Trop d\'essais : ce code est annulé, demandez-en un nouveau.']]);
            }
            $restants = self::ESSAIS_MAX - $enregistre->tentatives;
            throw ValidationException::withMessages(['code' => ["Code incorrect. Encore {$restants} essai".($restants > 1 ? 's' : '').'.']]);
        }

        $enregistre->delete();

        return $enregistre->email;
    }

    /** L'adresse qu'un code de vérification attend encore de confirmer, s'il y en a une. */
    public function adresseEnAttente(User $user): ?string
    {
        $code = $user->codesEmail()->where('usage', CodeEmail::VERIFICATION)->first();

        return $code && ! $code->expire() ? $code->email : null;
    }

    /** « m***@gmail.com » : assez pour reconnaître son adresse, pas pour la copier. */
    public static function masquer(string $email): string
    {
        [$local, $domaine] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domaine;
    }
}
