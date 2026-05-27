# LUKASSA Phase 1 — Sprint 0 Foundations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mettre en place les fondations de LUKASSA (infrastructure Docker PG+PostGIS+Redis, Laravel 12 propre avec packages, 21 migrations exécutées, repo git/GitHub) — sans aucun code métier.

**Architecture:** Docker Compose pour PostgreSQL 16 + PostGIS 3.4 + Redis 7. Laravel 12 fraîchement créé via `composer create-project`, configuré pour PG+Redis, avec Sanctum/Jetstream(Inertia)/Horizon/Spatial. Migrations PostgreSQL copiées depuis `_database/migrations/`. Repo git local poussé sur GitHub privé.

**Tech Stack:** Docker Desktop (cask), Homebrew, GitHub CLI, PHP 8.3, Composer 2.9, Laravel 12, PostgreSQL 16, PostGIS 3.4, Redis 7-alpine, Sanctum, Jetstream+Inertia, Horizon, predis, matanyadaev/laravel-eloquent-spatial.

**Référence spec :** `docs/superpowers/specs/2026-05-27-phase1-sprint0-foundations-design.md`

---

## Structure de fichiers cible (fin de Sprint 0)

```
/Applications/MAMP/htdocs/lukassa/
├── .gitignore                      ← créé (Task 5)
├── README.md                       ← créé (Task 20)
├── start.sh                        ← créé (Task 19)
├── stop.sh                         ← créé (Task 19)
├── docker/
│   └── docker-compose.yml          ← créé (Task 6)
├── backend/                         ← Laravel 12 fraîchement créé
│   ├── (tous les fichiers Laravel 12 standard)
│   ├── .env                        ← écrit (Task 11)
│   ├── database/migrations/         ← 21 fichiers copiés (Task 15) + ce que Jetstream ajoute
│   └── …
├── _database/                       ← INTACT (source de référence)
├── conception/                      ← INTACT
└── docs/superpowers/                ← spec + ce plan
```

---

## Task 1 : Installer Docker Desktop et GitHub CLI (en parallèle)

**Files:** (aucun fichier projet modifié — installation système)

- [ ] **Step 1.1 : Vérifier que Homebrew est à jour**

Run: `brew --version`
Expected: `Homebrew 5.x` ou supérieur.

- [ ] **Step 1.2 : Installer Docker Desktop ET GitHub CLI (parallèle Bash)**

Lancer les deux installations en parallèle (deux appels Bash dans le même message) :

Run (Bash 1): `brew install --cask docker`
Run (Bash 2): `brew install gh`

Expected: les deux installations se terminent sans erreur. Docker Desktop est dans `/Applications/Docker.app`.

- [ ] **Step 1.3 : Lancer Docker Desktop manuellement**

Run: `open -a Docker`
Expected: l'app Docker se lance. **STOP** : attendre 30s, puis l'utilisateur doit confirmer dans Docker.app qu'il est prêt (peut nécessiter mot de passe macOS au premier lancement pour le privileged helper).

- [ ] **Step 1.4 : Vérifier que Docker daemon est joignable**

Run: `docker info | head -10`
Expected: ligne `Server Version:` présente, pas d'erreur "Cannot connect to the Docker daemon".

Si erreur : afficher message clair "lance Docker.app et attends qu'il soit Running", puis attendre confirmation utilisateur.

- [ ] **Step 1.5 : Authentifier GitHub CLI (interactif)**

Run: `gh auth login`
Expected: prompt interactif (web ou device code). **STOP** : attendre que l'utilisateur termine le flow. Puis :

Run: `gh auth status`
Expected: ligne `✓ Logged in to github.com`.

---

## Task 2 : Sauvegarder le `.env` existant (sécurité)

**Files:**
- Lire: `/Applications/MAMP/htdocs/lukassa/backend/.env`
- Créer: `/tmp/lukassa-backend.env.bak`

- [ ] **Step 2.1 : Vérifier que le .env existe avant backup**

Run: `test -f /Applications/MAMP/htdocs/lukassa/backend/.env && echo "EXISTS" || echo "MISSING"`
Expected: `EXISTS`

- [ ] **Step 2.2 : Copier vers /tmp**

Run: `cp /Applications/MAMP/htdocs/lukassa/backend/.env /tmp/lukassa-backend.env.bak`
Expected: pas de sortie.

- [ ] **Step 2.3 : Vérifier la copie**

Run: `diff /Applications/MAMP/htdocs/lukassa/backend/.env /tmp/lukassa-backend.env.bak && echo "OK"`
Expected: `OK` (fichiers identiques).

---

## Task 3 : Effacer le contenu de `backend/` (préserver le dossier)

**Files:**
- Supprimer le contenu de: `/Applications/MAMP/htdocs/lukassa/backend/`

- [ ] **Step 3.1 : Lister le contenu avant suppression (sécurité)**

Run: `ls -la /Applications/MAMP/htdocs/lukassa/backend/ | head -20`
Expected: voir vendor, app, composer.json, .env, etc.

- [ ] **Step 3.2 : Supprimer tout le contenu (fichiers cachés inclus)**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && find . -mindepth 1 -delete`
Expected: pas d'erreur.

- [ ] **Step 3.3 : Vérifier que backend/ est vide**

Run: `ls -la /Applications/MAMP/htdocs/lukassa/backend/`
Expected: seulement `.` et `..` (dossier vide).

---

## Task 4 : Initialiser git + créer `.gitignore`

**Files:**
- Créer: `/Applications/MAMP/htdocs/lukassa/.gitignore`
- Init: `/Applications/MAMP/htdocs/lukassa/.git/`

- [ ] **Step 4.1 : Initialiser le repo git et renommer la branche en `main`**

Run: `cd /Applications/MAMP/htdocs/lukassa && git init && git branch -m main`
Expected: `Initialized empty Git repository in /Applications/MAMP/htdocs/lukassa/.git/`

- [ ] **Step 4.2 : Configurer l'identité git locale au repo (si pas globale)**

Run: `cd /Applications/MAMP/htdocs/lukassa && git config user.email | head -1 || echo "MISSING"`
Si `MISSING`, demander à l'utilisateur son email et name avant de poursuivre. Sinon continuer.

- [ ] **Step 4.3 : Créer le `.gitignore` à la racine**

Créer `/Applications/MAMP/htdocs/lukassa/.gitignore` avec le contenu :

```gitignore
# === macOS ===
.DS_Store
**/.DS_Store

# === Editor / IDE ===
.idea/
.vscode/
*.swp
*.swo
*~

# === Laravel (backend/) ===
backend/vendor/
backend/node_modules/
backend/.env
backend/.env.backup
backend/.phpunit.result.cache
backend/storage/*.key
backend/storage/logs/*.log
backend/storage/framework/cache/data/*
backend/storage/framework/sessions/*
backend/storage/framework/views/*
backend/storage/framework/testing/*
backend/storage/debugbar/*
backend/public/storage
backend/public/build/
backend/public/hot
backend/bootstrap/cache/*.php
!backend/storage/framework/cache/.gitignore
!backend/storage/framework/sessions/.gitignore
!backend/storage/framework/views/.gitignore
!backend/storage/logs/.gitignore

# === Node / Nuxt (web-portal/, Phase 4) ===
web-portal/node_modules/
web-portal/.nuxt/
web-portal/.output/
web-portal/dist/
web-portal/.env

# === Flutter (mobile/, Phase 4) ===
mobile/.dart_tool/
mobile/.flutter-plugins
mobile/.flutter-plugins-dependencies
mobile/.packages
mobile/build/
mobile/ios/Pods/
mobile/ios/.symlinks/
mobile/ios/Flutter/Flutter.framework
mobile/ios/Flutter/Flutter.podspec
mobile/android/.gradle/
mobile/android/local.properties
mobile/android/app/build/

# === Docker ===
docker/.env
docker/volumes/

# === Misc ===
*.log
*.pid
```

- [ ] **Step 4.4 : Vérifier que le `.gitignore` matche les bons fichiers**

Run: `cd /Applications/MAMP/htdocs/lukassa && git status --short | head -10`
Expected: voir `.gitignore` et `docs/` en untracked, ne PAS voir `_database/.DS_Store` ni `conception/.DS_Store`.

---

## Task 5 : Créer `docker/docker-compose.yml` et démarrer les conteneurs

**Files:**
- Créer: `/Applications/MAMP/htdocs/lukassa/docker/docker-compose.yml`

- [ ] **Step 5.1 : Vérifier qu'aucun process n'occupe les ports 5432 / 6379**

Run: `lsof -i :5432 2>/dev/null; lsof -i :6379 2>/dev/null; echo "---"`
Expected: aucune sortie (ports libres). Si occupé : **STOP**, signaler à l'utilisateur.

- [ ] **Step 5.2 : Créer le `docker-compose.yml`**

Créer `/Applications/MAMP/htdocs/lukassa/docker/docker-compose.yml` avec le contenu :

```yaml
services:
  postgres:
    image: postgis/postgis:16-3.4
    container_name: lukassa_postgres
    environment:
      POSTGRES_DB: lukassa
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: postgres
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 10s
      timeout: 5s
      retries: 5
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    container_name: lukassa_redis
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    command: redis-server --appendonly yes
    restart: unless-stopped

volumes:
  postgres_data:
  redis_data:
```

- [ ] **Step 5.3 : Démarrer les conteneurs**

Run: `cd /Applications/MAMP/htdocs/lukassa/docker && docker compose up -d`
Expected: pull des images (≈1-2 min première fois) puis :
```
[+] Running 2/2
 ✔ Container lukassa_postgres  Started
 ✔ Container lukassa_redis     Started
```

- [ ] **Step 5.4 : Attendre que PostgreSQL soit healthy (boucle, max 60s)**

Run:
```bash
for i in {1..12}; do
  status=$(docker inspect --format='{{.State.Health.Status}}' lukassa_postgres 2>/dev/null)
  echo "[$i] postgres health: $status"
  [ "$status" = "healthy" ] && break
  sleep 5
done
```
Expected: terminer par `healthy`. Si après 60s toujours `starting` ou `unhealthy` : **STOP**, signaler.

- [ ] **Step 5.5 : Vérifier les 2 conteneurs**

Run: `docker ps --filter "name=lukassa_" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"`
Expected: 2 lignes, postgres avec `(healthy)`, redis `Up`.

- [ ] **Step 5.6 : Activer l'extension PostGIS dans la base `lukassa`**

Run: `docker exec -i lukassa_postgres psql -U postgres -d lukassa -c "CREATE EXTENSION IF NOT EXISTS postgis;"`
Expected: `CREATE EXTENSION`

- [ ] **Step 5.7 : Vérifier la version PostGIS**

Run: `docker exec -i lukassa_postgres psql -U postgres -d lukassa -c "SELECT PostGIS_Version();"`
Expected: ligne contenant `3.4` ou `3.x`.

- [ ] **Step 5.8 : Vérifier que Redis répond**

Run: `docker exec -i lukassa_redis redis-cli ping`
Expected: `PONG`

---

## Task 6 : Créer le projet Laravel 12 propre

**Files:**
- Créer: tout `/Applications/MAMP/htdocs/lukassa/backend/*` via composer create-project

- [ ] **Step 6.1 : Lancer composer create-project (timeout 600s)**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && composer create-project laravel/laravel . "^12.0" --prefer-dist --no-interaction`
Expected: création de tous les fichiers Laravel 12, exit code 0. Le `composer create-project` exécute aussi `php artisan key:generate` automatiquement.

**Note timeout** : si la commande dépasse 600s (max Bash), relancer avec `composer install` après. La commande téléchargera ~120 packages.

- [ ] **Step 6.2 : Vérifier la version Laravel installée**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && php artisan --version`
Expected: `Laravel Framework 12.x.x`

- [ ] **Step 6.3 : Lister les fichiers générés (sanity check)**

Run: `ls /Applications/MAMP/htdocs/lukassa/backend/`
Expected: voir `app`, `bootstrap`, `config`, `database`, `routes`, `vendor`, `composer.json`, `.env`, etc.

---

## Task 7 : Installer les packages Composer requis

**Files:**
- Modifier: `/Applications/MAMP/htdocs/lukassa/backend/composer.json`

- [ ] **Step 7.1 : Installer les packages de production**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && composer require laravel/sanctum laravel/jetstream laravel/horizon predis/predis matanyadaev/laravel-eloquent-spatial laravel/tinker --no-interaction`
Expected: install + update du composer.lock. Aucune erreur.

- [ ] **Step 7.2 : Installer les packages dev**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && composer require --dev barryvdh/laravel-debugbar laravel/pail --no-interaction`
Expected: install OK.

- [ ] **Step 7.3 : Vérifier les packages présents**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && composer show | grep -E "(sanctum|jetstream|horizon|predis|eloquent-spatial|tinker|debugbar|pail)"`
Expected: 8 lignes, toutes présentes.

---

## Task 8 : Installer Jetstream avec Inertia (sans teams)

**Files:**
- Modifier: `/Applications/MAMP/htdocs/lukassa/backend/` (Jetstream publie scaffold)

- [ ] **Step 8.1 : Lancer jetstream:install inertia**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && php artisan jetstream:install inertia --no-interaction`
Expected: scaffold publié (resources/js/, etc.), pas d'erreur.

- [ ] **Step 8.2 : Vérifier que les vues Inertia sont publiées**

Run: `ls /Applications/MAMP/htdocs/lukassa/backend/resources/js/Pages/ 2>/dev/null | head -5`
Expected: voir au moins `Welcome.vue` ou équivalent Inertia.

---

## Task 9 : Écrire `backend/.env` configuré pour PG + Redis

**Files:**
- Écraser: `/Applications/MAMP/htdocs/lukassa/backend/.env`

- [ ] **Step 9.1 : Écrire le nouveau `.env` (APP_KEY sera régénérée à l'étape 9.3)**

Écraser `/Applications/MAMP/htdocs/lukassa/backend/.env` avec ce contenu (APP_KEY vide volontairement — sera remplie par `key:generate --force` à l'étape suivante) :

```dotenv
APP_NAME="LUKASSA API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_FRONTEND_URL=http://localhost:3000

APP_LOCALE=fr
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=fr_FR

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lukassa
DB_USERNAME=postgres
DB_PASSWORD=postgres

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

SANCTUM_STATEFUL_DOMAINS=localhost:8000,localhost:3000,localhost:8080
SESSION_DOMAIN=localhost

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@lukassa.local"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
```

- [ ] **Step 9.2 : Générer l'APP_KEY**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && php artisan key:generate --force`
Expected: `Application key set successfully.` (remplit APP_KEY dans .env).

- [ ] **Step 9.3 : Tester que Laravel peut lire la config DB**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && php artisan config:show database.default`
Expected: `database.default ...... pgsql`

---

## Task 10 : Storage link, Horizon, cache clear

**Files:**
- Créer: `/Applications/MAMP/htdocs/lukassa/backend/public/storage` (symlink)
- Modifier: `/Applications/MAMP/htdocs/lukassa/backend/config/horizon.php`

- [ ] **Step 10.1 : Publier les assets Horizon + créer le storage link**

Run:
```bash
cd /Applications/MAMP/htdocs/lukassa/backend && \
  php artisan horizon:install && \
  php artisan storage:link
```
Expected:
```
Horizon scaffolding installed successfully.
The [public/storage] link has been connected to [storage/app/public].
```

- [ ] **Step 10.2 : Nettoyer les caches Laravel**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear`
Expected: 4 lignes `INFO Cleared ...`.

---

## Task 11 : Tester la connexion PostgreSQL depuis Laravel

**Files:** (aucun — vérification)

- [ ] **Step 11.1 : Tester la connexion DB via tinker**

Run:
```bash
cd /Applications/MAMP/htdocs/lukassa/backend && \
  php artisan tinker --execute='echo DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION), PHP_EOL;'
```
Expected: ligne avec `16.x` (version PostgreSQL).

Si erreur "could not find driver" : `pdo_pgsql` n'est pas chargé → **STOP**, signaler à l'utilisateur d'activer l'extension ou utiliser le PHP MAMP.

- [ ] **Step 11.2 : Tester que PostGIS est accessible via PHP**

Run:
```bash
cd /Applications/MAMP/htdocs/lukassa/backend && \
  php artisan tinker --execute='echo DB::select("SELECT PostGIS_Version() AS v")[0]->v, PHP_EOL;'
```
Expected: version PostGIS 3.x.

---

## Task 12 : Inspecter 3 migrations `_database/` (sanity check syntaxe PG)

**Files:**
- Lire: `/Applications/MAMP/htdocs/lukassa/_database/migrations/2026_05_26_000001_create_users_table.php`
- Lire: `/Applications/MAMP/htdocs/lukassa/_database/migrations/2026_05_26_000008_create_rfqs_table.php`
- Lire: `/Applications/MAMP/htdocs/lukassa/_database/migrations/2026_05_26_000010_create_orders_table.php`

- [ ] **Step 12.1 : Lire la migration `users` (chercher uuid, phone, type)**

Run: lire `_database/migrations/2026_05_26_000001_create_users_table.php`. Chercher :
- `Schema::create('users', …)`
- Mention de UUID ou `uuid('id')->primary()`
- Champ `phone`, `type` (client/provider/admin)

Expected: champs cohérents avec la conception. Si syntaxe MySQL (`AUTO_INCREMENT`, `ENGINE=InnoDB`) : **STOP**, alerter.

- [ ] **Step 12.2 : Lire la migration `rfqs` (chercher geography Point 4326)**

Run: lire `_database/migrations/2026_05_26_000008_create_rfqs_table.php`. Chercher :
- Une référence à `geography`, `Point`, `4326`, ou `$table->point('location')`

Expected: présence d'un champ géospatial. Si absent : flag, mais ne pas bloquer (la conception le demande mais peut être ajouté en Phase 2).

- [ ] **Step 12.3 : Lire la migration `orders` (chercher relations FK)**

Run: lire `_database/migrations/2026_05_26_000010_create_orders_table.php`. Chercher :
- `foreignId('client_id')`, `foreignId('provider_id')`, `foreignId('rfq_id')`, etc.

Expected: foreign keys présentes.

---

## Task 13 : Supprimer les migrations Laravel par défaut et Sanctum conflictuelles

**Files:**
- Supprimer (si présents) :
  - `/Applications/MAMP/htdocs/lukassa/backend/database/migrations/0001_01_01_000000_create_users_table.php`
  - `/Applications/MAMP/htdocs/lukassa/backend/database/migrations/0001_01_01_000001_create_cache_table.php`
  - `/Applications/MAMP/htdocs/lukassa/backend/database/migrations/0001_01_01_000002_create_jobs_table.php`
  - `/Applications/MAMP/htdocs/lukassa/backend/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php` (si publié par Sanctum/Jetstream)

- [ ] **Step 13.1 : Lister les migrations actuelles**

Run: `ls /Applications/MAMP/htdocs/lukassa/backend/database/migrations/`
Expected: voir les migrations Laravel 12 par défaut + éventuellement celles publiées par Jetstream/Sanctum.

- [ ] **Step 13.2 : Supprimer les migrations conflictuelles (force, idempotent)**

Run:
```bash
cd /Applications/MAMP/htdocs/lukassa/backend/database/migrations && \
  rm -f 0001_01_01_000000_create_users_table.php \
        0001_01_01_000001_create_cache_table.php \
        0001_01_01_000002_create_jobs_table.php \
        2019_12_14_000001_create_personal_access_tokens_table.php
```
Expected: pas de sortie (rm -f n'erreur pas si fichier absent).

- [ ] **Step 13.3 : Vérifier qu'aucune autre migration ne va dupliquer une table de `_database/`**

Run: `ls /Applications/MAMP/htdocs/lukassa/backend/database/migrations/`
Expected: aucune migration dont le nom contient `create_users_table`, `create_personal_access_tokens_table`, `create_password_reset_tokens_table`, `create_failed_jobs_table`, `create_jobs_table`, `create_cache_table`. Si l'une de ces présente (autre que celles de `_database/` qu'on va copier après), **STOP**, signaler le conflit.

Garder les éventuelles migrations Jetstream type `add_two_factor_columns_to_users_table` ou `create_features_table` (pas de conflit).

---

## Task 14 : Copier les 21 migrations de `_database/` vers `backend/`

**Files:**
- Copier 21 fichiers de `/Applications/MAMP/htdocs/lukassa/_database/migrations/` vers `/Applications/MAMP/htdocs/lukassa/backend/database/migrations/`

- [ ] **Step 14.1 : Copier en une commande**

Run: `cp /Applications/MAMP/htdocs/lukassa/_database/migrations/2026_05_26_*.php /Applications/MAMP/htdocs/lukassa/backend/database/migrations/`
Expected: pas de sortie.

- [ ] **Step 14.2 : Vérifier que les 21 fichiers sont présents**

Run: `ls /Applications/MAMP/htdocs/lukassa/backend/database/migrations/2026_05_26_*.php | wc -l`
Expected: `21`

---

## Task 15 : Exécuter les migrations

**Files:** (aucun — modification de la DB seulement)

- [ ] **Step 15.1 : Afficher le statut avant migrate**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && php artisan migrate:status`
Expected: liste des migrations avec statut `Pending` pour les 21 (et éventuellement quelques Jetstream).

- [ ] **Step 15.2 : Exécuter les migrations**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && php artisan migrate --force`
Expected: chaque migration tournée, exit code 0.

Si erreur sur une migration spécifique : **STOP**, lire l'erreur, signaler à l'utilisateur le numéro de la migration en faute. Ne pas faire `migrate:fresh` sans accord.

- [ ] **Step 15.3 : Vérifier que les 21 tables existent dans PG**

Run:
```bash
docker exec -i lukassa_postgres psql -U postgres -d lukassa -c "\dt" | wc -l
```
Expected: ≥ 25 (21 tables métier + jetstream + en-têtes psql).

- [ ] **Step 15.4 : Vérifier la présence des tables clés (sanity)**

Run:
```bash
docker exec -i lukassa_postgres psql -U postgres -d lukassa -c \
  "SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename IN ('users','rfqs','bids','orders','transactions','wallets','geo_zones') ORDER BY tablename;"
```
Expected: 7 lignes, une par table.

---

## Task 16 : Vérifier que Laravel démarre et répond

**Files:** (aucun — vérification)

- [ ] **Step 16.1 : Lancer `php artisan serve` en arrière-plan**

Run (run_in_background=true): `cd /Applications/MAMP/htdocs/lukassa/backend && php artisan serve --port=8000`
Expected: notification de démarrage du process en background. Attendre 3s avant le check suivant.

- [ ] **Step 16.2 : Tester la homepage Laravel**

Run: `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000`
Expected: `200`

- [ ] **Step 16.3 : Tester la route login Jetstream (sanity Inertia)**

Run: `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/login`
Expected: `200`

- [ ] **Step 16.4 : Tester qu'Horizon démarre (puis l'arrêter)**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && timeout 5 php artisan horizon || true`
Expected: lignes Horizon démarré (ou timeout après 5s sans erreur fatale).

- [ ] **Step 16.5 : Arrêter le serveur Laravel background**

Identifier l'ID du process Bash background lancé en 16.1 et le tuer via KillShell.

---

## Task 17 : Premier commit local

**Files:** (commit git)

- [ ] **Step 17.1 : Vérifier le statut git avant commit**

Run: `cd /Applications/MAMP/htdocs/lukassa && git status --short | head -30`
Expected: voir `.gitignore`, `docker/`, `backend/` (sauf vendor/node_modules), `_database/`, `conception/`, `docs/` en untracked.

- [ ] **Step 17.2 : Ajouter les fichiers (par dossier, pas `git add .`)**

Run:
```bash
cd /Applications/MAMP/htdocs/lukassa && \
  git add .gitignore docker/ backend/ _database/ conception/ docs/ Liste-commande.rtf
```
Expected: pas d'erreur.

- [ ] **Step 17.3 : Vérifier ce qui est staged (sanity, pas de .env ni vendor)**

Run: `cd /Applications/MAMP/htdocs/lukassa && git diff --cached --stat | tail -20`
Expected: lots de fichiers, mais aucun chemin contenant `vendor/`, `node_modules/`, `.env`.

- [ ] **Step 17.4 : Si un .env est staged par erreur, le retirer**

Run: `cd /Applications/MAMP/htdocs/lukassa && git diff --cached --name-only | grep -E "(^|/)\.env$" || echo "OK"`
Expected: `OK`. Si on voit un .env staged, faire `git rm --cached <path>` avant de continuer.

- [ ] **Step 17.5 : Commit initial**

Run:
```bash
cd /Applications/MAMP/htdocs/lukassa && git commit -m "$(cat <<'EOF'
feat(sprint-0): LUKASSA foundations — Docker infra + Laravel 12 + 21 migrations

- Docker Compose : PostgreSQL 16 + PostGIS 3.4 + Redis 7
- Backend Laravel 12 propre avec Sanctum, Jetstream+Inertia, Horizon,
  predis, matanyadaev/laravel-eloquent-spatial, debugbar
- Configuration PG + Redis (cache/queue/sessions)
- 21 migrations PostgreSQL exécutées (depuis _database/)
- .gitignore complet (Laravel + Node + Flutter + Docker)
- Spec + plan dans docs/superpowers/

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```
Expected: commit créé, hash affiché.

- [ ] **Step 17.6 : Vérifier le commit**

Run: `cd /Applications/MAMP/htdocs/lukassa && git log --oneline -1`
Expected: une ligne avec le hash + message.

---

## Task 18 : Créer le repo GitHub privé et push

**Files:** (création remote)

- [ ] **Step 18.1 : Vérifier qu'aucun repo `lukassa` n'existe déjà sur le compte**

Run: `gh repo view lukassa --json name 2>&1 | head -3`
Expected: erreur `Could not resolve to a Repository` (le repo n'existe pas).

Si le repo existe déjà : **STOP**, demander à l'utilisateur (réutiliser, renommer, ou autre).

- [ ] **Step 18.2 : Créer le repo privé + remote + push initial**

Run:
```bash
cd /Applications/MAMP/htdocs/lukassa && \
  gh repo create lukassa --private --source=. --remote=origin --push
```
Expected: création + push réussi (main → origin/main).

- [ ] **Step 18.3 : Vérifier le repo GitHub**

Run: `gh repo view lukassa --json visibility,defaultBranchRef -q .`
Expected: `{"visibility":"PRIVATE","defaultBranchRef":{"name":"main"}}` (ou équivalent).

---

## Task 19 : Créer les scripts `start.sh` et `stop.sh`

**Files:**
- Créer: `/Applications/MAMP/htdocs/lukassa/start.sh`
- Créer: `/Applications/MAMP/htdocs/lukassa/stop.sh`

- [ ] **Step 19.1 : Écrire `start.sh`**

Créer `/Applications/MAMP/htdocs/lukassa/start.sh` avec :

```bash
#!/bin/bash
set -e

ROOT="$(cd "$(dirname "$0")" && pwd)"

echo "==> Démarrage des conteneurs Docker (PG + Redis)..."
cd "$ROOT/docker" && docker compose up -d

echo "==> Attente que PostgreSQL soit healthy..."
for i in {1..12}; do
  status=$(docker inspect --format='{{.State.Health.Status}}' lukassa_postgres 2>/dev/null || echo "missing")
  [ "$status" = "healthy" ] && break
  sleep 5
done
[ "$status" != "healthy" ] && echo "ERREUR : postgres pas healthy après 60s" && exit 1

echo "==> Lancement Laravel + Horizon en arrière-plan..."
cd "$ROOT/backend"
php artisan migrate --force 2>/dev/null || true
nohup php artisan horizon > "$ROOT/.horizon.log" 2>&1 &
echo $! > "$ROOT/.horizon.pid"
nohup php artisan serve --port=8000 > "$ROOT/.serve.log" 2>&1 &
echo $! > "$ROOT/.serve.pid"

echo ""
echo "LUKASSA démarré :"
echo "  API : http://localhost:8000"
echo "  Logs Horizon : tail -f $ROOT/.horizon.log"
echo "  Logs serve   : tail -f $ROOT/.serve.log"
```

- [ ] **Step 19.2 : Écrire `stop.sh`**

Créer `/Applications/MAMP/htdocs/lukassa/stop.sh` avec :

```bash
#!/bin/bash
set -e

ROOT="$(cd "$(dirname "$0")" && pwd)"

echo "==> Arrêt des processus PHP..."
[ -f "$ROOT/.serve.pid" ] && kill "$(cat $ROOT/.serve.pid)" 2>/dev/null && rm "$ROOT/.serve.pid" || true
[ -f "$ROOT/.horizon.pid" ] && kill "$(cat $ROOT/.horizon.pid)" 2>/dev/null && rm "$ROOT/.horizon.pid" || true

echo "==> Arrêt des conteneurs Docker..."
cd "$ROOT/docker" && docker compose down

echo "LUKASSA arrêté."
```

- [ ] **Step 19.3 : Rendre les scripts exécutables**

Run: `chmod +x /Applications/MAMP/htdocs/lukassa/start.sh /Applications/MAMP/htdocs/lukassa/stop.sh`
Expected: pas de sortie.

- [ ] **Step 19.4 : Tester `start.sh` (les conteneurs sont déjà up, donc idempotent)**

Run: `/Applications/MAMP/htdocs/lukassa/start.sh`
Expected: voir le message "LUKASSA démarré" et les processus en arrière-plan.

- [ ] **Step 19.5 : Vérifier que l'API répond après start.sh**

Run: `sleep 3 && curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000`
Expected: `200`

- [ ] **Step 19.6 : Arrêter via stop.sh**

Run: `/Applications/MAMP/htdocs/lukassa/stop.sh`
Expected: messages d'arrêt, conteneurs stoppés.

---

## Task 20 : Écrire le `README.md` racine

**Files:**
- Créer: `/Applications/MAMP/htdocs/lukassa/README.md`

- [ ] **Step 20.1 : Écrire le README**

Créer `/Applications/MAMP/htdocs/lukassa/README.md` avec :

```markdown
# LUKASSA

Plateforme de mise en relation de services en Afrique.
Triple front : portail web (Nuxt.js 3), app mobile (Flutter), backoffice (Laravel + Inertia).

## Stack

- **Backend** : Laravel 12 (PHP 8.3), Sanctum, Jetstream+Inertia, Horizon
- **DB** : PostgreSQL 16 + PostGIS 3.4 (Docker)
- **Cache/Queue/Sessions** : Redis 7 (Docker)
- **Géospatial** : `matanyadaev/laravel-eloquent-spatial`

## Démarrage rapide

Prérequis : Docker Desktop, PHP 8.3, Composer 2.9, Node 25+, GitHub CLI.

```bash
# Cloner
git clone git@github.com:<owner>/lukassa.git
cd lukassa

# Installer Laravel
cd backend
composer install
cp .env.example .env   # puis adapter si besoin
php artisan key:generate

# Démarrer
cd ..
./start.sh
```

API : http://localhost:8000

## Structure

- `backend/`       — Laravel 12 (API + backoffice Inertia)
- `web-portal/`    — Nuxt.js 3 (vitrine + dashboard) — Phase 4
- `mobile/`        — Flutter 3 (Android/iOS) — Phase 4
- `docker/`        — docker-compose (PG + Redis)
- `_database/`     — migrations PostgreSQL de référence
- `conception/`    — cahier des charges + maquettes
- `docs/superpowers/` — specs + plans de développement

## Commandes utiles

```bash
./start.sh                          # Démarrer tout
./stop.sh                           # Tout arrêter

# Backend
cd backend
php artisan migrate:status          # Statut DB
php artisan tinker                  # REPL
php artisan horizon                 # Queue worker
```

## Phases

- ✅ **Phase 1** — Sprint 0 Foundations (infra + Laravel propre + migrations)
- ⏳ **Phase 2** — Sprints 1-4 : API métier (Auth, Catégories, RFQ, Bids/Orders)
- ⏳ **Phase 3** — Sprints 5-7 : Paiements Mobile Money, messagerie, backoffice
- ⏳ **Phase 4** — Web portal Nuxt + App mobile Flutter

Voir `docs/superpowers/specs/` et `docs/superpowers/plans/`.

## Licence

YUBILE TECHNOLOGIE — Confidentiel.
```

- [ ] **Step 20.2 : Vérifier le README**

Run: `head -10 /Applications/MAMP/htdocs/lukassa/README.md`
Expected: voir les premières lignes du README.

---

## Task 21 : Commit final + push

**Files:** (commit + push)

- [ ] **Step 21.1 : Ajouter README.md et scripts**

Run: `cd /Applications/MAMP/htdocs/lukassa && git add README.md start.sh stop.sh`
Expected: pas d'erreur.

- [ ] **Step 21.2 : Commit**

Run:
```bash
cd /Applications/MAMP/htdocs/lukassa && git commit -m "$(cat <<'EOF'
chore: README + scripts start/stop

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```
Expected: commit créé.

- [ ] **Step 21.3 : Push vers origin/main**

Run: `cd /Applications/MAMP/htdocs/lukassa && git push origin main`
Expected: push OK.

- [ ] **Step 21.4 : Vérifier sur GitHub**

Run: `gh repo view lukassa --web 2>/dev/null || gh repo view lukassa`
Expected: voir le repo avec les 2 commits.

---

## Critères finaux de Definition of Done (à vérifier)

Une fois toutes les tâches ci-dessus complétées :

- [ ] `docker info` répond (engine running)
- [ ] `docker ps --filter "name=lukassa_"` montre 2 conteneurs, postgres healthy
- [ ] `docker exec lukassa_postgres psql -U postgres -d lukassa -c "SELECT PostGIS_Version();"` renvoie une version 3.x
- [ ] `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000` renvoie `200` (avec serve actif)
- [ ] `php artisan tinker --execute='echo DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);'` renvoie 16.x
- [ ] `php artisan migrate:status | grep -c "Ran"` ≥ 21
- [ ] `gh repo view lukassa --json visibility -q .visibility` renvoie `PRIVATE`
- [ ] `docker exec lukassa_redis redis-cli ping` renvoie `PONG`
