#!/usr/bin/env bash
#
# Cloud Agent start phase (attached): bring MySQL up, then run the app server.
#
# `start` supports a long-running attached process, so we reconcile MySQL (via
# cloud-agent-start.sh) and then hand off to the Laravel dev server in the
# foreground. The built Vite assets produced during install mean the app is
# fully rendered without a separate Vite process; run `composer dev` manually
# if you want hot-module reloading while developing.
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"

# Ensure MySQL is running and ready (idempotent).
bash "$here/cloud-agent-start.sh"

cd "$here/.."

echo "==> Serving the application on http://0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
