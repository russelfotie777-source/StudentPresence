# Présence — IUT de Douala

Système de suivi des présences en cours pour l'IUT de Douala (Cameroun). Il
remplace la feuille de présence papier : le délégué géolocalise la salle, les
étudiants confirment leur présence depuis leur téléphone si la distance
correspond, les heures réellement effectuées par les enseignants sont
enregistrées, et l'administration en tire les listes de présence officielles et
le suivi des vacations.

Trois applications, un seul dépôt :

| Dossier | Rôle | Pile |
|---|---|---|
| `presence-api/` | API REST et règles métier | Laravel 13, PHP 8.4, MySQL, Sanctum |
| `presence-app/` | Application mobile (étudiant, délégué, enseignant) | Next.js 16, React 19, Tailwind v4 |
| `presence-admin/` | Back-office de l'administration | Next.js 16, shadcn/base-ui |
| `legacy-php/` | Première version (PHP sans framework), conservée pour référence | — |

## Démarrer en local

Prérequis : PHP 8.4, Composer, Node 20+, MySQL, et Ghostscript (`gs`) pour la
lecture des PDF par l'assistant.

```bash
# API
cd presence-api
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoDataSeeder   # jeu de démonstration (facultatif)
php artisan serve --port=8001

# File d'attente (rappels de pointage, imports de l'assistant)
php artisan queue:work --queue=assistant,default --timeout=3600

# Planificateur (rappels minutés) — en production, un cron :
#   * * * * * cd /chemin/presence-api && php artisan schedule:run
php artisan schedule:work

# Application mobile
cd ../presence-app && npm install && npm run dev          # http://localhost:3000

# Back-office
cd ../presence-admin && npm install && npm run dev -- --port 3001
```

Les deux applications lisent l'URL de l'API dans `NEXT_PUBLIC_API_URL`
(`.env.local`, voir `.env.local.example`).

## Modèle de données

`users` (rôles `Etudiant`, `Delegue`, `Enseignant`, `Admin`) — `departements`
(GI, GRT… : sommet de la structure, chacun existe à tous les niveaux) —
`filieres` (une par département et par niveau au minimum, plus les options) —
`salles` (une classe : nom, filière, formation FI ou FA) — `niveaux` —
`matieres` — `course_templates` (cours récurrent : matière, enseignant, salle,
jour, horaires, période de validité) — `seances` (une occurrence par semaine du
semestre) — `semaines` (calendrier du semestre) — `presences_etudiants` —
`positions_seances` (point GPS du délégué) — `promotions_temporaires` (délégué
remplaçant) — `requetes_enseignants` — `tarifs_heures`.

Les étudiants en formation migrante (FM) sont rattachés à une salle FI et
signalés comme tels sur les listes officielles.

Tout ce qui touche à l'heure ou au jour se calcule dans le fuseau de
l'établissement (`Africa/Douala`), jamais sur l'horloge de l'appareil : les
applications lisent `GET /api/heure`.

## Ce que fait l'application

- **Pointage géolocalisé** : le délégué envoie la position de la salle, les
  étudiants pointent dans une fenêtre de ±15 minutes autour de la séance et à
  moins de 120 m du point de référence.
- **Second facteur facial** (facultatif, réglable par grade) à la connexion.
- **Rappels** de pointage dans l'application et en notification push.
- **Back-office** : emploi du temps en grille hebdomadaire, feuille de présence
  par salle et par semaine, gestion des comptes étudiants (rattachement,
  restriction, blocage), validations, requêtes des enseignants, tarifs horaires.
- **Liste de présence officielle** au format du département, en PDF.
- **Assistant** (API Claude, facultatif — sans clé il se désactive) : importe un
  emploi du temps ou une liste d'étudiants depuis un PDF, un tableur ou un
  message, et propose des actions que l'administrateur valide avant application.

## Tests

```bash
cd presence-api && php artisan test
```

## Conventions

Le domaine et l'interface sont en français (`Etudiant`, `seance`, `salle`,
`filiere`, `matiere`) ; suivre l'existant pour tout ajout. Le code PHP est
formaté par Pint (`vendor/bin/pint --dirty`), le TypeScript par ESLint
(`npx eslint src`).
