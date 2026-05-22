#!/usr/bin/env bash
# Wait for PostgreSQL to be reachable before continuing.
set -euo pipefail

HOST="${DB_HOST:-db}"
PORT="${DB_PORT:-5432}"
TIMEOUT="${DB_WAIT_TIMEOUT:-60}"

echo "[wait-for-db] waiting for ${HOST}:${PORT} (timeout ${TIMEOUT}s)..."
for ((i = 1; i <= TIMEOUT; i++)); do
    if (echo > "/dev/tcp/${HOST}/${PORT}") >/dev/null 2>&1; then
        echo "[wait-for-db] ${HOST}:${PORT} is up"
        exit 0
    fi
    sleep 1
done

echo "[wait-for-db] timeout waiting for ${HOST}:${PORT}" >&2
exit 1
