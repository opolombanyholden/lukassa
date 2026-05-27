#!/bin/bash
set -e

ROOT="$(cd "$(dirname "$0")" && pwd)"

echo "==> Arrêt des processus PHP..."
if [ -f "$ROOT/.serve.pid" ]; then
  kill "$(cat "$ROOT/.serve.pid")" 2>/dev/null || true
  rm -f "$ROOT/.serve.pid"
fi
if [ -f "$ROOT/.horizon.pid" ]; then
  kill "$(cat "$ROOT/.horizon.pid")" 2>/dev/null || true
  rm -f "$ROOT/.horizon.pid"
fi

echo "==> Arrêt des conteneurs Docker..."
cd "$ROOT/docker" && docker compose down

echo "LUKASSA arrêté."
