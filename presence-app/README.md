# Présence — application mobile

Application des étudiants, délégués et enseignants (Next.js 16, React 19,
Tailwind v4, React Query). Vue d'ensemble du projet : `README.md` à la racine.

## Démarrer

```bash
npm install
cp .env.local.example .env.local    # NEXT_PUBLIC_API_URL=http://localhost:8001
npm run dev                          # http://localhost:3000
```

L'API doit tourner en parallèle (`presence-api`, port 8001).

## Ce qu'on y fait

- **Étudiant** : séances du jour, pointage de présence géolocalisé, historique,
  taux d'assiduité, notifications et rappels de pointage.
- **Délégué** : envoi de la position de la salle, appel de la classe,
  désignation d'un remplaçant temporaire.
- **Enseignant** : séances, déclaration d'heures, requêtes, suivi des vacations.

La connexion peut exiger un second facteur facial selon le grade (réglable
depuis le back-office). Le service worker (`public/sw.js`) reçoit les rappels
push ; sur iPhone ils n'existent qu'une fois l'application installée sur
l'écran d'accueil.

## Repères

- `src/app/(auth)/` connexion, inscription, reconnaissance faciale —
  `src/app/(app)/` écrans authentifiés, avec la navigation du bas.
- `src/hooks/` un fichier par domaine (séances, présences, notifications…) ;
  toutes les requêtes passent par `src/lib/api-client.ts`.
- L'heure et la date affichées viennent de l'API (`useHeureDouala`), jamais de
  l'horloge du téléphone.

```bash
npx eslint src      # style
npm run build       # vérification avant livraison
```
