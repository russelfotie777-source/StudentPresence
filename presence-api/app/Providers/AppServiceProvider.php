<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
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
    }
}
