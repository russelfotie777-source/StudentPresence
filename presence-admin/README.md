# Présence — back-office

Interface d'administration du département (Next.js 16, React 19, shadcn /
base-ui, React Query). Vue d'ensemble du projet : `README.md` à la racine.

## Démarrer

```bash
npm install
cp .env.local.example .env.local    # NEXT_PUBLIC_API_URL=http://localhost:8001
npm run dev -- --port 3001           # http://localhost:3001
```

L'API doit tourner en parallèle (`presence-api`, port 8001). Le compte
d'administration se crée en ligne de commande : `php artisan app:make-admin "Nom" 690000000 motdepasse`.

## Les écrans

- **Vue d'ensemble** — ce qui attend une décision, activité de la semaine.
- **Catalogue** — niveaux, filières, salles, matières.
- **Emplois du temps** — grille hebdomadaire par salle ou par enseignant :
  cliquer un créneau libre pour programmer un cours, une séance pour la
  déplacer ou l'annuler ; calendrier du semestre.
- **Étudiants** — feuille de présence d'une salle sur une semaine ; corriger une
  présence d'un clic, gérer un compte (salle, restriction, blocage), imprimer la
  liste officielle en PDF (coche/croix ou +1/−1, au choix).
- **Validations**, **Requêtes enseignants**, **Migrations FA → FI**,
  **Historique des séances**, **Tarifs horaires**, **Reconnaissance faciale**.
- **Assistant** (facultatif) — importe un emploi du temps ou une liste
  d'étudiants depuis un PDF, un tableur ou un message, et propose des actions à
  valider avant application.

Toutes les dates et heures affichées viennent de l'API (`useHeureDouala`), dans
le fuseau de l'établissement.

```bash
npx eslint src      # style
npm run build       # vérification avant livraison
```
