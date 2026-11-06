#!/usr/bin/env bash
#
# Zero-surprise deploy for a single app server (run from the project root as
# the deploy user). Fails fast on any error and never leaves the app in
# maintenance mode on success.
#
#   ./deploy.sh            # deploy the current branch
#   BRANCH=main ./deploy.sh
#
set -euo pipefail

BRANCH="${BRANCH:-$(git rev-parse --abbrev-ref HEAD)}"
PHP="${PHP:-php}"

say() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

say "Preflight"
[[ -f .env ]] || { echo "No .env file. Copy .env.example and fill it in first."; exit 1; }
env_value() { grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | tr -d '"' ; }
if [[ "$(env_value APP_ENV)" == "production" && "$(env_value APP_DEBUG)" != "false" ]]; then
    echo "Refusing to deploy: APP_ENV=production requires APP_DEBUG=false (stack traces would be public)."
    exit 1
fi

say "Maintenance mode on"
"$PHP" artisan down --retry=15 --refresh=15 || true
# Bring the app back up even if a later step fails, so a bad deploy is not also an outage.
trap '"$PHP" artisan up' EXIT

say "Pulling ${BRANCH}"
git pull --ff-only origin "${BRANCH}"

say "PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

say "Front-end build"
npm ci --no-audit --no-fund
npm run build

say "Database"
"$PHP" artisan migrate --force
# Idempotent: new permissions or default rows for every existing tenant.
"$PHP" artisan tenancy:provision

say "Caches"
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache

say "Workers"
# Horizon's supervisor (systemd/Supervisor) restarts it with the new code.
"$PHP" artisan horizon:terminate
"$PHP" artisan queue:restart

say "Done: $(git rev-parse --short HEAD) is live"
