# LUKASSA

Plateforme de mise en relation de services en Afrique.
Triple front : portail web (Nuxt.js 3), app mobile (Flutter), backoffice (Laravel + Inertia).

## Stack

- **Backend** : Laravel 12 (PHP 8.3), Sanctum, Jetstream + Inertia, Horizon
- **DB** : PostgreSQL 16 + PostGIS 3.4 (Docker)
- **Cache / Queue / Sessions** : Redis 7 (Docker)
- **Géospatial** : `matanyadaev/laravel-eloquent-spatial`

## Démarrage rapide

Prérequis : Docker Desktop, PHP 8.3, Composer 2.9, Node 20+, Git.

```bash
git clone https://github.com/opolombanyholden/lukassa.git
cd lukassa

# Installer Laravel
cd backend
composer install
cp /tmp/lukassa-backend.env.bak .env  # ou créer un .env (cf. docs/superpowers/specs/)
php artisan key:generate

# Lancer toute la stack
cd ..
./start.sh
```

API : `http://localhost:8001`

## Structure

| Dossier | Contenu |
|---|---|
| `backend/` | Laravel 12 (API + backoffice Inertia) |
| `web-portal/` | Nuxt.js 3 (vitrine + dashboard) — Phase 4 |
| `mobile/` | Flutter 3 (Android / iOS) — Phase 4 |
| `docker/` | `docker-compose.yml` (PG + Redis) |
| `_database/` | Migrations PostgreSQL de référence (source) |
| `conception/` | Cahier des charges + maquettes |
| `docs/superpowers/` | Specs et plans de développement |

## Commandes utiles

```bash
./start.sh                          # Démarrer toute la stack
./stop.sh                           # Tout arrêter

# Backend (depuis backend/)
php artisan migrate:status          # Statut DB
php artisan tinker                  # REPL
php artisan horizon                 # Queue worker
```

## Phases du projet

- [x] **Phase 1 — Sprint 0 Foundations** : infra Docker + Laravel propre + 21 migrations
- [ ] **Phase 2 — Sprints 1-4** : API métier (Auth/OTP, Catégories, RFQ géolocalisé, Bids/Orders)
- [ ] **Phase 3 — Sprints 5-7** : Paiements Mobile Money, messagerie, reviews, backoffice
- [ ] **Phase 4** : Web portal Nuxt + App mobile Flutter

Voir `docs/superpowers/specs/` et `docs/superpowers/plans/`.

## Licence

YUBILE TECHNOLOGIE — Confidentiel.
