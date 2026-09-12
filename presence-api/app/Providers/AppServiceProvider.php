<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API REST simple pour un frontend maison (pas de contrat JSON:API à
        // respecter) : pas d'enveloppe "data" superflue sur les ressources.
        JsonResource::withoutWrapping();

        $this->definirLimitesDeDebit();
    }

    /**
     * Limites des routes non authentifiées, où le limiteur ne peut pas
     * compter par utilisateur.
     *
     * Compter par adresse IP seule serait une faute ici : tout le campus sort
     * par la même adresse publique, et 200 étudiants qui se connectent à 8 h
     * se bloqueraient mutuellement. La connexion est donc comptée par compte
     * visé ET par adresse — cinq essais sur un même identifiant depuis une
     * même adresse, ce qui borne la devinette de mot de passe sans jamais
     * gêner deux personnes distinctes.
     *
     * L'inscription n'a pas de compte à protéger : une limite par adresse
     * suffit, large pour ne pas entraver une salle entière qui s'inscrit
     * pendant un même TP.
     */
    private function definirLimitesDeDebit(): void
    {
        RateLimiter::for('connexion', function (Request $request) {
            $compte = mb_strtolower(trim((string) $request->input('phone')));

            return Limit::perMinute(5)->by($compte.'|'.$request->ip());
        });

        RateLimiter::for('inscription', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
