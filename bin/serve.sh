#!/usr/bin/env bash
set -e

PORT="${1:-8000}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [ ! -f "$ROOT/database/retro.sqlite" ]; then
    php "$ROOT/database/migrate.php"
fi

echo "RetroApp en http://localhost:$PORT"
php -S "localhost:$PORT" -t "$ROOT/public" "$ROOT/public/router.php"
