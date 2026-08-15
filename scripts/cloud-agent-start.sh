#!/usr/bin/env bash
#
# Cloud Agent start phase: per-boot runtime reconciliation.
#
# The install phase provisions the MySQL data directory; here we only ensure the
# daemon is running and ready before the agent's terminals (app server, Vite,
# queue worker) start. Safe to run on every boot.
set -euo pipefail

# The socket/PID directory lives on tmpfs and is not recreated when a pod boots
# from a snapshot, so ensure it exists (owned by the mysql user) before start.
echo "==> Ensuring MySQL runtime directory"
sudo install -d -o mysql -g mysql -m 0755 /var/run/mysqld

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

echo "MySQL did not become ready in time; recent error log:" >&2
sudo tail -n 80 /var/log/mysql/error.log 2>/dev/null >&2 || true
exit 1
