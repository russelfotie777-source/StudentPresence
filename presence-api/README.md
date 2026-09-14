# Présence — API

API REST et règles métier de l'application de présence de l'IUT de Douala
(Laravel 13, PHP 8.4, MySQL, authentification par jetons Sanctum). Vue
d'ensemble du projet et des trois applications : voir le `README.md` à la racine
du dépôt.

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoDataSeeder   # jeu de démonstration (facultatif)
php artisan serve --port=8001
```

Deux processus complètent le serveur web :

```bash
php artisan queue:work --queue=assistant,default --timeout=3600   # rappels, imports
php artisan schedule:work                                          # rappels minutés
```

En production, le planificateur passe par un cron :
`* * * * * cd /chemin/presence-api && php artisan schedule:run`

## Configuration

Tout est dans `.env` (voir `.env.example`, commenté) :

- `APP_TIMEZONE` reste `Africa/Douala` : toutes les décisions horaires en
  dépendent (fenêtre de pointage, séances du jour, rappels).
- `PRESENCE_*` : distance et précision GPS acceptées, durée de session admin,
  en-tête des documents officiels.
- `VAPID_*` : clés des notifications push (`php artisan webpush:vapid`).
- `ANTHROPIC_API_KEY` : assistant du back-office ; laissé vide, il se désactive
  proprement.

## Structure

- `app/Http/Controllers/Api/` — un contrôleur par domaine, routes dans
  `routes/api.php` (groupes : public, authentifié, admin).
- `app/Services/` — règles métier partagées entre plusieurs chemins :
  génération des séances, détection de conflits, feuille de présence, liste
  hebdomadaire, rappels, assistant.
- `app/Enums/` — rôles, formations, états de présence, statuts de compte.
- `tests/Feature/` — l'essentiel de la couverture ; chaque règle métier y est
  vérifiée à travers l'API.

## Tests et style

```bash
php artisan test              # suite complète
php artisan test --filter=X   # un fichier ou un test
vendor/bin/pint --dirty       # formatage avant commit
```
