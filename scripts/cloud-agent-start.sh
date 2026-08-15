#!/usr/bin/env bash
#
# Cloud Agent start phase: per-boot runtime reconciliation.
#
# The install phase provisions the MySQL data directory; here we only ensure the
# daemon is running and ready before the agent's terminals (app server, Vite,
# queue worker) start. Safe to run on every boot.
set -euo pipefail

echo "==> Starting MySQL"
sudo service mysql start || true

echo "==> Waiting for MySQL to accept connections"
for _ in $(seq 1 60); do
    if sudo mysqladmin ping >/dev/null 2>&1; then
        echo "MySQL is ready"
        exit 0
    fi
    sleep 1
done

echo "MySQL did not become ready in time" >&2
exit 1
