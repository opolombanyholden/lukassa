# LUKASSA — Phase 1 / Sprint 0 Foundations — Design

**Date** : 2026-05-27
**Auteur** : Daisy + Claude (brainstorming session)
**Statut** : à valider
**Périmètre** : Sprint 0 backend uniquement (infra Docker + Laravel 12 propre + migrations PostgreSQL + git/GitHub). Aucune logique métier, aucun front (Nuxt/Flutter).

---

## 1. Contexte & motivation

LUKASSA est une plateforme de mise en relation de services en Afrique (cf. `conception/conception_full_v3.html` et `conception/Cahier Des Charges Lukassa Complet.docx`). L'architecture cible v2.0 prévoit :

- Backend Laravel 11+ (API REST `/api/v1/`) avec Sanctum, Horizon
- PostgreSQL 16 + PostGIS (recherche géolocalisée pour le module RFQ)
- Redis (cache, queues, sessions)
- Triple front : site vitrine + portail Nuxt 3, backoffice Inertia/Vue, app Flutter
- 8 sprints / 15 semaines

**État actuel** (`/Applications/MAMP/htdocs/lukassa/`) :
- `backend/` contient un Laravel 13.8 partiellement installé, à effacer (décision utilisateur).
- `_database/migrations/` contient 21 migrations PostgreSQL prêtes (à conserver, à copier).
- `conception/` contient les documents de référence (à conserver intacts).
- `docker/`, `web-portal/`, `mobile/` sont vides.
- Le projet **n'est pas un repo git**.
- Aucun PostgreSQL, Docker ou Flutter installé sur la machine.

**Décomposition du projet en phases** (chaque phase a son propre spec/plan) :

| Phase | Périmètre | Sprints conception |
|---|---|---|
| **Phase 1 (ce spec)** | Sprint 0 — Foundations (infra + Laravel propre + migrations) | Sprint 0 backend |
| Phase 2 | API métier — Auth/OTP, Catégories, RFQ, Bids/Orders | Sprints 1-4 |
| Phase 3 | Paiements Mobile Money, messagerie, reviews, backoffice | Sprints 5-7 |
| Phase 4 | Web portal Nuxt + App mobile Flutter | UI en parallèle des sprints |

---

## 2. Architecture cible Phase 1

```
/Applications/MAMP/htdocs/lukassa/   ← repo git, push GitHub privé (origin/main)
├── .gitignore                       ← Laravel + Node + Flutter + Docker volumes
├── README.md                        ← commandes start.sh / stop.sh
├── docker/
│   └── docker-compose.yml           ← postgis/postgis:16-3.4 + redis:7-alpine
├── backend/                         ← Laravel 12 PROPRE (table rase)
│   ├── app/, config/, routes/, …
│   ├── database/migrations/         ← 21 fichiers copiés depuis _database/
│   ├── .env                         ← DB_CONNECTION=pgsql, Redis local
│   └── …
├── _database/migrations/            ← INTACT (source de référence)
├── conception/                      ← INTACT
├── docs/superpowers/specs/          ← ce document
├── web-portal/                      ← vide (Phase 4)
└── mobile/                          ← vide (Phase 4)
```

**Services en exécution local après Sprint 0** :
- PostgreSQL 16 + PostGIS sur `127.0.0.1:5432` (conteneur Docker `lukassa_postgres`)
- Redis 7 sur `127.0.0.1:6379` (conteneur Docker `lukassa_redis`)
- Laravel `php artisan serve` sur `http://localhost:8000` (lancé à la demande)
- Horizon (queue worker) — lancement à la demande

---

## 3. Décisions techniques

| Sujet | Décision | Justification |
|---|---|---|
| Version Laravel | **Laravel 12.x** | LTS 2026, compatible PHP 8.3, proche conventions Laravel 11 de la conception |
| Base de données | **PostgreSQL 16 + PostGIS 3.4** via Docker | Conformité conception, géolocalisation requise pour RFQ |
| Cache/Queue/Session | **Redis 7-alpine** | Conformité conception |
| Auth scaffold | **Jetstream + Inertia** (sans teams) | Conformité Liste-commande.rtf |
| Client Redis | **predis/predis** (pas phpredis) | Pas d'extension PHP système à installer |
| Spatial Laravel | **matanyadaev/laravel-eloquent-spatial v4** | Casts `geography(Point,4326)` natifs |
| Environnement Docker | **Docker Desktop via Homebrew Cask** | Installation reproductible |
| Versioning | **Git local + GitHub privé** via `gh repo create` | Traçabilité dès le Sprint 0 |
| Chemin projet | **`/Applications/MAMP/htdocs/lukassa`** (conservé) | Évite de déplacer les fichiers existants |

---

## 4. Composants

### 4.1 — `docker/docker-compose.yml`

```yaml
services:
  postgres:
    image: postgis/postgis:16-3.4
    container_name: lukassa_postgres
    environment:
      POSTGRES_DB: lukassa
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: postgres
    ports: ["5432:5432"]
    volumes: [postgres_data:/var/lib/postgresql/data]
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 10s
      timeout: 5s
      retries: 5
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    container_name: lukassa_redis
    ports: ["6379:6379"]
    volumes: [redis_data:/data]
    command: redis-server --appendonly yes
    restart: unless-stopped

volumes:
  postgres_data:
  redis_data:
```

### 4.2 — Backend Laravel — packages composer

```bash
composer create-project laravel/laravel . "^12.0" --prefer-dist
composer require \
  laravel/sanctum \
  laravel/jetstream \
  laravel/horizon \
  predis/predis \
  matanyadaev/laravel-eloquent-spatial \
  laravel/tinker
composer require --dev barryvdh/laravel-debugbar laravel/pail
php artisan jetstream:install inertia --no-interaction
```

### 4.3 — `backend/.env` (template écrit après composer create-project)

```dotenv
APP_NAME="LUKASSA API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_FRONTEND_URL=http://localhost:3000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lukassa
DB_USERNAME=postgres
DB_PASSWORD=postgres

BROADCAST_CONNECTION=log
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

SANCTUM_STATEFUL_DOMAINS=localhost:8000,localhost:3000,localhost:8080
SESSION_DOMAIN=localhost
```

Différences vs Liste-commande.rtf :
- `BROADCAST_DRIVER` → `BROADCAST_CONNECTION` (Laravel 11+)
- `CACHE_DRIVER` → `CACHE_STORE` (Laravel 11+)
- Ajout `REDIS_CLIENT=predis`

### 4.4 — `.gitignore` racine (esquisse)

Couvre :
- Laravel : `backend/vendor/`, `backend/node_modules/`, `backend/.env`, `backend/storage/logs/*.log`, `backend/storage/framework/cache/*`, `backend/storage/framework/sessions/*`, `backend/storage/framework/views/*`, `backend/public/storage`, `backend/public/build/`, `backend/public/hot`, `backend/.phpunit.result.cache`
- Node (Nuxt futur) : `web-portal/node_modules/`, `web-portal/.nuxt/`, `web-portal/.output/`, `web-portal/dist/`
- Flutter futur : `mobile/.dart_tool/`, `mobile/build/`, `mobile/ios/Pods/`, `mobile/.flutter-plugins*`
- Docker : `docker/.env`, `docker/volumes/`
- macOS : `.DS_Store` (toute arborescence)
- IDE : `.idea/`, `.vscode/`, `*.swp`

### 4.5 — Migrations PostgreSQL

**Migrations Laravel 12 par défaut à supprimer** (contenus regroupés dans Laravel 11+, conflits avec `_database/`) :
- `0001_01_01_000000_create_users_table.php` — contient `users` + `password_reset_tokens` + `sessions`. Conflit avec nos `000001` et `000002`. La table `sessions` n'est PAS dans `_database/` : c'est OK car le projet utilise Redis pour les sessions (`SESSION_DRIVER=redis`).
- `0001_01_01_000001_create_cache_table.php` — Redis utilisé pour le cache.
- `0001_01_01_000002_create_jobs_table.php` — contient `jobs` + `job_batches` + `failed_jobs`. Conflit avec nos `000019` (failed_jobs) et `000020` (jobs). **Risque** : la table `job_batches` n'est PAS dans `_database/`. Pas bloquant pour Sprint 0 (pas de queue batched). À ajouter en Phase 2 si besoin.

**Migration Sanctum à supprimer si publiée** (par `jetstream:install` ou `vendor:publish`) :
- `…_create_personal_access_tokens_table.php` (généralement préfixé `2019_12_14_000001_…`). Conflit avec `_database/2026_05_26_000003_create_personal_access_tokens_table.php`. **Garder la version `_database/`** (probablement adaptée pour UUID).

**Migrations Jetstream éventuelles à vérifier** :
- Selon la version, Jetstream peut publier `…_add_two_factor_columns_to_users_table.php` ou `…_create_features_table.php`. À conserver telles quelles (pas de conflit a priori avec `_database/`). Si un conflit apparaît, signaler et demander.

**Puis copier** les 21 fichiers de `_database/migrations/` vers `backend/database/migrations/` :

**Puis copier** les 21 fichiers de `_database/migrations/` vers `backend/database/migrations/` :

```
2026_05_26_000001_create_users_table.php
2026_05_26_000002_create_password_reset_tokens_table.php
2026_05_26_000003_create_personal_access_tokens_table.php
2026_05_26_000004_create_profiles_table.php
2026_05_26_000005_create_categories_table.php
2026_05_26_000006_create_services_table.php
2026_05_26_000007_create_provider_services_table.php
2026_05_26_000008_create_rfqs_table.php
2026_05_26_000009_create_bids_table.php
2026_05_26_000010_create_orders_table.php
2026_05_26_000011_create_transactions_table.php
2026_05_26_000012_create_wallets_table.php
2026_05_26_000013_create_withdrawals_table.php
2026_05_26_000014_create_reviews_table.php
2026_05_26_000015_create_notifications_table.php
2026_05_26_000016_create_messages_table.php
2026_05_26_000017_create_disputes_table.php
2026_05_26_000018_create_geo_zones_table.php
2026_05_26_000019_create_failed_jobs_table.php
2026_05_26_000020_create_jobs_table.php
2026_05_26_000021_create_subscriptions_table.php
```

**Avant `php artisan migrate`** : activer l'extension PostGIS :
```bash
docker exec -i lukassa_postgres psql -U postgres -d lukassa -c "CREATE EXTENSION IF NOT EXISTS postgis;"
```

**Pré-inspection** (à faire lors de l'exécution, pas du design) : ouvrir 3 migrations pour vérifier la syntaxe PostgreSQL (notamment `geography(Point, 4326)` pour RFQ). Si MySQL détecté, arrêter et alerter l'utilisateur.

---

## 5. Séquence d'exécution (10 étapes)

| # | Étape | Commande clé | Bloquant |
|---|---|---|---|
| 1 | Installer Docker Desktop | `brew install --cask docker` puis lancer Docker.app | Oui (étape 6) |
| 2 | Installer GitHub CLI | `brew install gh` puis `gh auth login` (interactif) | Oui (étape 10) |
| 3 | Backup `.env` actuel | `cp backend/.env /tmp/lukassa-backend.env.bak` | Non |
| 4 | Effacer contenu `backend/` | `rm -rf backend/{.[!.],}*` (garde le dossier) | Oui (étape 7) |
| 5 | `git init` + branche main + `.gitignore` racine | `git init && git branch -m main` + Write `.gitignore` | Oui (étape 10) |
| 6 | Créer `docker/docker-compose.yml` + `docker compose up -d` | Wait sur healthcheck postgres | Oui (étape 9) |
| 7 | `composer create-project laravel/laravel . "^12.0"` + packages + `jetstream:install inertia` | Voir 4.2 | Oui (étape 8) |
| 8 | Écrire `backend/.env` + `php artisan key:generate` + `php artisan storage:link` + `php artisan horizon:install` | Voir 4.3 | Oui (étape 9) |
| 9 | `CREATE EXTENSION postgis` + supprimer migrations Laravel par défaut + copier `_database/migrations/*` + `php artisan migrate` | Voir 4.5 | Oui (validation) |
| 10 | `gh repo create lukassa --private --source=. --remote=origin --push` + commit initial | Si repo existe, arrêter et demander | Final |

**Parallélisme possible** : étapes 1 et 2 (deux Bash en parallèle), étapes 3 et 4 (lecture + suppression indépendantes).

---

## 6. Gestion des erreurs & risques

| Risque | Probabilité | Stratégie |
|---|---|---|
| Docker Desktop non lancé après brew install | Élevée | `docker info` en boucle avec timeout. Si KO après 60s : message "lance Docker.app manuellement, puis dis-moi go". |
| `gh auth login` interactif | Certaine | S'arrêter, attendre confirmation `gh auth status` avant étape 10. |
| `composer create-project` lent | Moyenne | timeout Bash 600s. |
| Conflit port 5432 ou 6379 | Faible | `lsof -i :5432` avant `docker compose up`. Si occupé : arrêter et demander à l'utilisateur. |
| Migrations `_database/` en MySQL | Inconnue | Inspecter 3 fichiers avant copie. Si MySQL : arrêter, alerter, ne pas réparer en silence. |
| Conflit migration `users` Jetstream/Laravel vs `_database` | Élevée | Supprimer migrations par défaut Laravel avant copie (cf. 4.5). |
| `jetstream:install` interactif | Élevée | Forcer `--no-interaction`. |
| `key:generate` sans `.env` | Moyenne | Écrire `.env` AVANT `key:generate`. |
| PostGIS pas créé avant migrate | Élevée | `CREATE EXTENSION postgis` AVANT `migrate`. |
| Repo GitHub `lukassa` existe déjà | Inconnue | `gh repo view lukassa` en check. Si existe : demander à l'utilisateur. |

**Stratégie globale** :
- Aucune action destructive en silence.
- Arrêt à la première erreur non triviale, demande à l'utilisateur.
- Étape de validation après chaque bloc critique (Docker up, migrate, push).
- Aucun retry automatique sur commandes interactives.

---

## 7. Critères de succès (Definition of Done)

| # | Critère | Commande de vérification | Résultat attendu |
|---|---|---|---|
| 1 | Docker engine tourne | `docker info` | Engine running, version ≥ 24.x |
| 2 | Conteneurs healthy | `docker ps --filter "name=lukassa_"` | 2 lignes, postgres `(healthy)` |
| 3 | PostGIS installé | `docker exec lukassa_postgres psql -U postgres -d lukassa -c "SELECT PostGIS_Version();"` | Version 3.x |
| 4 | Laravel répond | `php artisan serve &` puis `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000` | `200` |
| 5 | Connexion DB | `php artisan tinker --execute='dd(DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION));'` | PostgreSQL 16.x |
| 6 | 21 migrations Ran | `php artisan migrate:status \| grep -c "Ran"` | 21 |
| 7 | Repo GitHub privé OK | `gh repo view lukassa --json visibility,defaultBranchRef -q .` | `private` + `main` |

**Bonus** :
- Horizon démarre : `php artisan horizon` (CTRL+C pour arrêter)
- Redis répond : `docker exec lukassa_redis redis-cli ping` → `PONG`

**Definition of Done** :
> Un nouveau dev qui clone le repo et exécute `cd docker && docker compose up -d && cd ../backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan serve` obtient une API Laravel vide mais opérationnelle, connectée à PG+PostGIS+Redis, avec les 21 tables créées.

---

## 8. Hors périmètre (à NE PAS faire dans ce Sprint 0)

- ❌ Module Auth (register/login/OTP) — Sprint 1 / Phase 2
- ❌ Module RFQ, Bid, Order, Payment, Geo, Notification — Sprints 3-6 / Phase 2-3
- ❌ Routes API métier (`/api/v1/...`) — Phase 2
- ❌ Site vitrine Nuxt — Phase 4
- ❌ App mobile Flutter — Phase 4 (Flutter pas encore installé sur la machine)
- ❌ Backoffice Inertia/Vue avec écrans métier — Phase 3
- ❌ Mobile Money adapters — Sprint 5 / Phase 3
- ❌ FCM, SMS, Mail — Phase 2-3
- ❌ Seeders & factories métier — Phase 2 (sauf le `DatabaseSeeder` vide par défaut)
- ❌ CI/CD GitHub Actions — Phase 3 ou plus tard
- ❌ Tests unitaires métier — au fil des phases

Le scaffold Jetstream (login/register Inertia généré) **reste en place** par défaut. Il sera adapté en Phase 2 (passage en API + SMS OTP).

---

## 9. Dépendances vers les phases suivantes

À la fin de Phase 1, la Phase 2 peut démarrer immédiatement avec :
- Repo git initialisé, branche `main` poussée sur GitHub.
- Backend Laravel 12 vide mais structuré (`app/Http/Controllers/`, `app/Models/`, `routes/api.php`).
- 21 tables PostgreSQL en place avec PostGIS.
- Redis disponible pour queues/cache/sessions.
- Composer et npm dépendances installées.

La Phase 4 (Flutter) nécessitera **un spec séparé** pour installer Flutter (`brew install --cask flutter`) et initialiser le projet `mobile/`.
