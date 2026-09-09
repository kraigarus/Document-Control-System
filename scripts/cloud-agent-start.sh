#!/usr/bin/env bash
#
# Cloud Agent start phase: per-boot runtime reconciliation.
#
# The install phase provisions the MySQL data directory; here we only ensure the
# daemon is running and ready before the agent's terminals (app server, Vite,
# queue worker) start. Safe to run on every boot.
#
# We start mysqld directly with --daemonize rather than via the Debian
# `service mysql start` init script: the init script relies on systemd/state
# that is not present when a pod boots from an environment-build snapshot, and
# fails there without emitting a usable error. `mysqld --daemonize` forks into
# the background and only returns once the server is ready, which is exactly the
# contract a start script needs.
set -euo pipefail

echo "==> Preparing MySQL runtime state"
# The socket/PID dir lives on tmpfs and is not restored from a snapshot.
sudo install -d -o mysql -g mysql -m 0755 /var/run/mysqld
# Re-assert datadir ownership in case the booting pod uses a different context.
sudo chown -R mysql:mysql /var/lib/mysql /var/run/mysqld

if sudo mysqladmin ping >/dev/null 2>&1; then
    echo "MySQL is already running"
    exit 0
fi

echo "==> Starting mysqld"
if ! sudo mysqld --user=mysql --daemonize --pid-file=/var/run/mysqld/mysqld.pid 2>/tmp/mysqld-start.err; then
    echo "mysqld failed to start. Startup stderr:" >&2
    sudo cat /tmp/mysqld-start.err >&2 || true
    echo "--- /var/log/mysql/error.log (tail) ---" >&2
    sudo tail -n 80 /var/log/mysql/error.log 2>/dev/null >&2 || true
    exit 1
fi

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
