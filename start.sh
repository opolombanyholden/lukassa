#!/bin/bash
set -e

ROOT="$(cd "$(dirname "$0")" && pwd)"

echo "==> Démarrage des conteneurs Docker (PG + Redis)..."
cd "$ROOT/docker" && docker compose up -d

echo "==> Attente que PostgreSQL soit healthy..."
H=""
for i in $(seq 1 12); do
  H=$(docker inspect --format='{{.State.Health.Status}}' lukassa_postgres 2>/dev/null || echo "missing")
  [ "$H" = "healthy" ] && break
  sleep 5
done
if [ "$H" != "healthy" ]; then
  echo "ERREUR : postgres pas healthy après 60s (status: $H)"
  exit 1
fi
echo "PostgreSQL healthy."

echo "==> Lancement Laravel + Horizon en arrière-plan..."
cd "$ROOT/backend"
php artisan migrate --force 2>/dev/null || true
nohup php artisan horizon > "$ROOT/.horizon.log" 2>&1 &
echo $! > "$ROOT/.horizon.pid"
nohup php artisan serve --port=8001 > "$ROOT/.serve.log" 2>&1 &
echo $! > "$ROOT/.serve.pid"

echo ""
echo "LUKASSA démarré :"
echo "  API     : http://localhost:8001"
echo "  Logs    : tail -f $ROOT/.serve.log   et   tail -f $ROOT/.horizon.log"
echo "  Stop    : ./stop.sh"
