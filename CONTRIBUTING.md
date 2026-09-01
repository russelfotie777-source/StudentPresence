# Guide pour rejoindre le projet Présence

Salut, bienvenue sur le projet. Ce document est là pour t'aider à comprendre comment tout fonctionne, comment installer le projet chez toi, et comment on va travailler ensemble sur GitHub. Pas besoin de tout retenir du premier coup, tu pourras toujours revenir dessus.

## C'est quoi Présence

Présence est l'application utilisée par notre campus pour gérer les présences en cours. Un délégué de classe géolocalise la salle depuis son téléphone, les étudiants confirment leur présence en comparant leur position GPS à celle du délégué, les horaires réels des séances sont enregistrés, et l'administration peut ensuite éditer des listes de présence en PDF et calculer la paie des enseignants selon leurs heures réelles.

L'application avait déjà été utilisée l'année dernière, avec une ancienne version du site faite en PHP simple, sans framework (tu la trouveras dans le dossier legacy_php si jamais tu veux voir à quoi ça ressemblait). Cette année tous les étudiants passent en L3, donc on en a profité pour reconstruire le projet depuis zéro avec des outils plus modernes, plus solides, et un rendu beaucoup plus soigné.

## Comment le projet est organisé

Le dépôt contient trois applications séparées qui communiquent entre elles.

presence_api est le serveur. Il est écrit en Laravel, un framework PHP, et c'est lui qui gère la base de données, l'authentification, et toute la logique métier : calculer un salaire, vérifier une distance GPS, générer un emploi du temps, etc. Les deux autres applications lui envoient des requêtes pour récupérer ou modifier des données, il ne fait aucun affichage lui même.

presence_app est l'application que les étudiants, les délégués et les enseignants utilisent depuis leur téléphone. C'est là que se passe l'essentiel du travail visuel : le tableau de bord du jour, le pointage, l'historique des séances, le salaire des enseignants, les demandes et contestations, etc.

presence_admin est le back office, utilisé par l'administration depuis un ordinateur. C'est là qu'on gère les niveaux, les filières, les salles, les emplois du temps, et qu'on valide les comptes des délégués et des enseignants.

Les deux applications front, presence_app et presence_admin, sont construites avec Next.js et TypeScript, stylées avec Tailwind, et utilisent une librairie appelée Framer Motion pour les animations. C'est du React assez classique dans sa logique, donc si tu as déjà touché à React tu ne seras pas perdu, sinon ce sera l'occasion d'apprendre sur un vrai projet.

## Le vocabulaire du projet

Toute la base de données et une bonne partie du code utilisent des mots français plutôt qu'anglais, parce que c'est plus clair pour toute l'équipe. Voici les mots que tu vas croiser souvent. Une séance, c'est un cours prévu à un horaire donné. Une salle, c'est une salle de classe. Une filière, c'est une spécialité d'études. Un niveau, c'est l'année : L1, L2 ou L3. Un délégué, c'est un étudiant désigné pour gérer le pointage de sa classe pendant les séances. C'est important de garder ce vocabulaire français dans le code que tu écris toi aussi, pour que tout reste cohérent d'un bout à l'autre du projet.

## Installer le projet chez toi

Il te faut PHP 8.3 ou plus récent, Node 20 ou plus récent, et une base MySQL qui tourne en local.

Pour l'API, ouvre le dossier presence_api, installe les dépendances avec composer install, copie le fichier .env.example en .env, crée une base de données MySQL qui s'appelle presence_api, puis lance les migrations avec php artisan migrate. Tu peux aussi lancer php artisan db:seed --class=DemoDataSeeder pour remplir la base avec des données de démonstration réalistes plutôt que de tout créer à la main pour tester. Ensuite tu démarres le serveur avec php artisan serve --port=8001.

Pour presence_app et presence_admin, c'est plus simple. Tu ouvres le dossier, tu fais npm install, puis npm run dev. presence_app démarre sur le port 3000 et presence_admin sur le port 3001. Les deux vont chercher leurs données sur l'API à l'adresse http://localhost:8001, c'est déjà configuré dans leurs fichiers .env.local.

## Comment on travaille sur GitHub

Je sais que tu n'as jamais travaillé en équipe sur GitHub, donc je t'explique comment ça se passe chez nous. Ce n'est pas compliqué une fois qu'on a compris le principe.

Le code du projet vit sur GitHub, dans ce qu'on appelle un dépôt, ou repo. La version stable et à jour du projet s'appelle la branche main. On ne modifie jamais main directement. Chaque fois que tu veux travailler sur quelque chose, une nouvelle fonctionnalité ou une correction, tu crées une nouvelle branche à partir de main, avec un nom qui décrit ce que tu fais, par exemple feature/couleur-boutons ou fix/bug-connexion.

Tu fais tes modifications sur cette branche. Un commit, c'est une sauvegarde de ton travail avec un petit message qui explique ce que tu as changé, tu en fais autant que tu veux au fur et à mesure. Une fois que tu es content de ton travail, tu pousses ta branche sur GitHub.

À partir de là, tu ouvres ce qu'on appelle une pull request, une PR. C'est une demande qui dit en gros voilà ce que j'ai fait sur ma branche, est ce qu'on peut l'ajouter à main. On regarde le code ensemble, on en discute si besoin, et une fois que c'est bon on merge la PR, c'est à dire qu'on fusionne ta branche dans main. C'est ce système qui permet à plusieurs personnes de travailler sur le même projet en même temps sans se marcher dessus.

Si tu n'as jamais utilisé git en ligne de commande, les commandes de base dont tu vas te servir tous les jours sont celles ci. git checkout -b nom-de-ta-branche pour créer une nouvelle branche. git add . puis git commit -m "ton message" pour sauvegarder tes changements. git push pour les envoyer sur GitHub, la première fois il faudra ajouter -u origin nom-de-ta-branche. Si un mot ne te parle pas, demande, ou cherche cinq minutes sur internet, c'est un des trucs les plus documentés qui existent.

## Ce que tu peux apporter

Tu vas surtout travailler sur le front, donc presence_app et presence_admin.

presence_app est déjà assez avancée niveau design, avec un mode sombre et un mode clair, des animations un peu partout, une identité visuelle assez posée. Tu pourras surtout y ajouter des fonctionnalités ou peaufiner des détails plutôt que tout reconstruire.

presence_admin par contre est resté beaucoup plus basique visuellement. Il fait le travail mais n'a pas reçu le même soin que presence_app, donc il y a clairement de la place pour l'améliorer et le rendre aussi soigné.

Si tu veux toucher un peu au backend aussi, l'API Laravel est organisée de façon assez simple à suivre. Chaque route est déclarée dans routes/api.php, et pointe vers une méthode d'un contrôleur dans app/Http/Controllers/Api. Regarde comment un contrôleur déjà écrit fonctionne avant d'en ajouter un nouveau, le style est toujours à peu près le même d'un fichier à l'autre, donc c'est facile de s'en inspirer.

N'hésite surtout pas à demander si un truc n'est pas clair. Mieux vaut poser une question toute simple que de rester bloqué deux heures dessus tout seul.
