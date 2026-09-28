# Déploiement en production

Architecture recommandée pour ce dépôt :

| Service | Hôte | Plateforme |
|---|---|---|
| Application utilisateur | `https://ziris.vercel.app` | Vercel, projet `ziris`, racine `presence-app` |
| Administration | `https://ziris-administration.vercel.app` | Vercel, projet `ziris-administration`, racine `presence-admin` |
| API | `https://api.mboamarkets.com` | iFastNet, racine web `api.ziris/public` |

Remplacer `CPANEL_USER` et les noms de base de données dans les commandes
ci-dessous. Ne jamais versionner les valeurs secrètes.

## 1. DNS

1. Les projets Vercel `ziris` et `ziris-administration` sont reliés au dépôt
   GitHub et exposés par leurs domaines `vercel.app` respectifs.
2. Un domaine personnalisé pourra être ajouté plus tard sans modifier
   l'application.
3. Dans cPanel, faire pointer le sous-domaine `api.mboamarkets.com` vers :

   ```text
   /home/CPANEL_USER/api.ziris/public
   ```

4. Activer le certificat Let's Encrypt. Ne pas ouvrir l'application aux
   utilisateurs tant que `https://api.mboamarkets.com/up` ne répond pas en
   HTTPS.

## 2. API Laravel sur iFastNet

Prérequis cPanel : PHP 8.4, MySQL, extensions `curl`, `dom`, `fileinfo`, `gd`,
`mbstring`, `openssl`, `pdo_mysql`, `xml`, `zip`, cron et, idéalement, SSH.

Le code complet doit rester dans `/home/CPANEL_USER/api.ziris`; seul son
dossier `public` est exposé par Apache. Ne jamais placer `.env`, `vendor`,
`storage` ou la racine Laravel dans un dossier publiquement accessible.

Créer la base et son utilisateur dans cPanel, puis importer le projet par Git,
SFTP ou l'outil de fichiers. Depuis la racine Laravel :

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env
php artisan key:generate
chmod -R ug+rwX storage bootstrap/cache
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Si Composer n'est pas disponible en SSH, exécuter `composer install --no-dev
--prefer-dist --optimize-autoloader` localement avec PHP 8.4, puis envoyer
aussi le dossier `vendor`.

Configuration minimale du `.env` de production :

```dotenv
APP_NAME="Ziris API"
APP_ENV=production
APP_KEY=base64:VALEUR_GENEREE_PAR_ARTISAN
APP_DEBUG=false
APP_URL=https://api.mboamarkets.com

CORS_ALLOWED_ORIGINS=https://ziris.vercel.app,https://ziris-administration.vercel.app
# Autorise également les déploiements Preview générés par Vercel.
CORS_ALLOWED_ORIGIN_PATTERNS=/^https:\/\/ziris(?:-[a-z0-9-]+)?\.vercel\.app$/,/^https:\/\/ziris-administration(?:-[a-z0-9-]+)?\.vercel\.app$/

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=CPANEL_USER_presence
DB_USERNAME=CPANEL_USER_presence
DB_PASSWORD=MOT_DE_PASSE_LONG_ET_UNIQUE

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=3660

FILESYSTEM_DISK=local
BCRYPT_ROUNDS=12
PRESENCE_ADMIN_SESSION_HOURS=12
PRESENCE_MOT_DE_PASSE_INITIAL=MOT_DE_PASSE_INITIAL_A_REMPLACER

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=SERVEUR_SMTP
MAIL_PORT=587
MAIL_USERNAME=COMPTE_SMTP
MAIL_PASSWORD=MOT_DE_PASSE_SMTP
MAIL_FROM_ADDRESS=ziris@mboamarkets.com
MAIL_FROM_NAME="Ziris"

VAPID_SUBJECT=mailto:ziris@mboamarkets.com
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=

ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-opus-5
ASSISTANT_TAILLE_MAX_FICHIER_MO=25
```

Générer les clés push après avoir installé les dépendances :

```bash
php artisan webpush:vapid
php artisan optimize
```

Pour les pièces jointes de 25 Mo, régler dans cPanel PHP Options :

```ini
upload_max_filesize=30M
post_max_size=32M
memory_limit=512M
max_execution_time=120
```

Les PDF longs de l'assistant nécessitent `gs` (Ghostscript). Vérifier avec
`gs --version`; sans ce binaire, les images et tableurs restent disponibles,
mais le découpage des PDF longs doit rester désactivé.

## 3. Cron et file d'attente

Ajouter dans cPanel > Cron Jobs, toutes les minutes :

```cron
* * * * * cd /home/CPANEL_USER/api.ziris && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

L'assistant IA utilise une file de base de données. Ajouter un second cron ;
`flock` empêche deux imports longs de tourner en même temps :

```cron
* * * * * flock -n /tmp/ziris-assistant.lock sh -c 'cd /home/CPANEL_USER/api.ziris && /usr/local/bin/php artisan queue:work database --queue=assistant,default --stop-when-empty --max-jobs=1 --tries=1 --timeout=3600' >> /home/CPANEL_USER/api.ziris/storage/logs/queue.log 2>&1
```

Le chemin PHP varie selon le serveur. Le confirmer avec `which php` en SSH ou
avec l'interface MultiPHP de cPanel. Si `flock` n'existe pas, demander à
iFastNet le mécanisme de verrouillage disponible avant d'activer ce cron.

## 4. Projets Vercel

Importer deux fois le même dépôt GitHub :

### Application utilisateur

- Projet : `ziris`
- Framework : Next.js
- Root Directory : `presence-app`
- Build Command : `npm run build`
- Variable Production et Preview :
  `NEXT_PUBLIC_API_URL=https://api.mboamarkets.com`
- Domaine : `https://ziris.vercel.app`

### Administration

- Projet : `ziris-administration`
- Framework : Next.js
- Root Directory : `presence-admin`
- Build Command : `npm run build`
- Variable Production et Preview :
  `NEXT_PUBLIC_API_URL=https://api.mboamarkets.com`
- Domaine : `https://ziris-administration.vercel.app`

La variable `NEXT_PUBLIC_API_URL` est publique par conception : elle ne
contient aucun secret. Toute modification de cette variable exige un nouveau
déploiement Vercel.

## 5. Ordre de mise en ligne

1. Déployer l'API et vérifier `GET https://api.mboamarkets.com/up`.
2. Créer l'admin initial :
   `php artisan app:make-admin "Nom complet" "6XXXXXXXX" "mot-de-passe"`.
3. Déployer les deux projets Vercel en Preview.
4. Tester connexion admin, préinscription, première connexion utilisateur,
   création d'un cours, pointage, PDF/Excel, e-mail et notification push.
5. Promouvoir les previews validées en Production, puis rattacher les domaines.
6. Activer les cron jobs seulement après la migration et les tests manuels.

## 6. Vérifications après déploiement

```bash
curl -fsS https://api.mboamarkets.com/up
curl -fsS https://api.mboamarkets.com/api/heure
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
```

Dans Vercel, contrôler les logs des deux derniers déploiements. Dans cPanel,
contrôler `storage/logs/laravel.log`, l'usage CPU/RAM, l'espace disque et les
sauvegardes MySQL quotidiennes pendant la première semaine.
