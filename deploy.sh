#!/bin/bash
#
# Production deploy for grinmuzik.com.
#
# Invoked by .github/workflows/deploy.yml over SSH, after the validate job has
# passed. Safe to run by hand too.
#
# MUST run as the `grinmuzik` user. PHP-FPM runs as `grinmuzik`, and files
# written here by any other user leave the app 500ing on permission errors.
#
set -euo pipefail

export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"

APP_DIR="/home/grinmuzik/dson-music"
BACKUP_DIR="/home/grinmuzik/backups"
KEEP_BACKUPS=10

cd "$APP_DIR"

if [ "$(id -un)" != "grinmuzik" ]; then
    echo "FATAL: deploy must run as 'grinmuzik' (currently $(id -un))." >&2
    echo "       PHP-FPM runs as grinmuzik; files owned by anyone else 500 the site." >&2
    exit 1
fi

echo "==> Backing up the database before anything can migrate it..."
mkdir -p "$BACKUP_DIR"
# Credentials are read out of .env rather than passed on the command line, so
# they never appear in `ps` or the CI log.
DB_DATABASE=$(sed 's/^[[:space:]]*//' .env | grep -E '^DB_DATABASE=' | cut -d= -f2- | tr -d '"'"'"'')
DB_USERNAME=$(sed 's/^[[:space:]]*//' .env | grep -E '^DB_USERNAME=' | cut -d= -f2- | tr -d '"'"'"'')
DB_PASSWORD=$(sed 's/^[[:space:]]*//' .env | grep -E '^DB_PASSWORD=' | cut -d= -f2- | tr -d '"'"'"'')
DB_HOST=$(sed 's/^[[:space:]]*//' .env | grep -E '^DB_HOST=' | cut -d= -f2- | tr -d '"'"'"'')

BACKUP_FILE="${BACKUP_DIR}/${DB_DATABASE}-$(date +%Y%m%d-%H%M%S).sql.gz"

MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="${DB_HOST:-127.0.0.1}" \
    --user="$DB_USERNAME" \
    --single-transaction \
    --quick \
    --routines \
    "$DB_DATABASE" | gzip > "$BACKUP_FILE"

echo "    Backup: $BACKUP_FILE"

# Keep the directory from growing without bound.
ls -1t "${BACKUP_DIR}/${DB_DATABASE}-"*.sql.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm --

echo "==> Pulling latest code..."
# Pin the branch rather than trusting whatever happens to be checked out.
# A bare `git pull` deploys whichever branch someone last left the working copy
# on, and fails outright if that branch has no upstream -- which is exactly how
# the first real deploy died.
DEPLOY_BRANCH="${DEPLOY_BRANCH:-livestream}"

git fetch --prune origin "$DEPLOY_BRANCH"

if [ "$(git rev-parse --abbrev-ref HEAD)" != "$DEPLOY_BRANCH" ]; then
    echo "    switching from $(git rev-parse --abbrev-ref HEAD) to $DEPLOY_BRANCH"
    git checkout "$DEPLOY_BRANCH"
fi

# --ff-only so a deploy can never quietly create a merge commit on production.
git merge --ff-only "origin/$DEPLOY_BRANCH"

echo "==> Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Building frontend assets..."
npm ci --prefer-offline --no-audit --no-fund
npm run build

# Caches are rebuilt AFTER the build, because view:cache compiles Blade that
# references the Vite manifest.
echo "==> Clearing & rebuilding caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Restarting queue workers..."
php artisan queue:restart

echo "==> Smoke-testing the routes this deploy could have broken..."
# A green test suite is not proof the live app boots: config caching, the real
# .env and the real filesystem are all only exercised here.
php artisan tinker --execute='
    $checks = ["/", "/login", "/register", "/contact", "/manifest.webmanifest"];
    $failed = [];
    foreach ($checks as $uri) {
        try {
            $status = app()->handle(Illuminate\Http\Request::create($uri, "GET"))->getStatusCode();
        } catch (\Throwable $e) {
            $status = "EXCEPTION: " . $e->getMessage();
        }
        if ($status !== 200) {
            $failed[] = "{$uri} => {$status}";
        }
    }
    if ($failed) {
        fwrite(STDERR, "SMOKE TEST FAILED:\n  " . implode("\n  ", $failed) . "\n");
        exit(1);
    }
    echo "    all routes 200\n";
'

echo "==> Done."
