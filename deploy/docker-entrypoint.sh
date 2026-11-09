#!/bin/sh
# Runs on every container start, before the web server.
set -e

cd /app

# Schema changes are applied automatically; migrations are idempotent.
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Seeds the demo catalog only when the products table is empty (no-op afterwards).
php bin/console app:seed --no-interaction

exec "$@"
