#!/bin/bash
#
# Deploy Script - Claude Scraper
# Usage: sudo bash deploy.sh [--fresh]
#
# --fresh: First-time setup (creates database + runs migrations + seeds admin)
# No flags: Regular deploy (git pull + composer + migrations + permissions)
#

set -e

APP_DIR="/var/www/html/scraper"
WEBUSER="www-data"
FRESH_INSTALL=false

# Database connection (reads from .env)
source_env() {
    if [ -f "$APP_DIR/.env" ]; then
        export $(grep -v '^#' "$APP_DIR/.env" | xargs)
    fi
}

# Parse arguments
for arg in "$@"; do
    case $arg in
        --fresh) FRESH_INSTALL=true ;;
    esac
done

echo "========================================="
echo "  Deploying Claude Scraper"
echo "========================================="

# Check we're in the right directory
if [ ! -d "$APP_DIR" ]; then
    echo "ERROR: $APP_DIR does not exist."
    echo "Clone the repo first: git clone https://github.com/jjohnson2458/scraper.git $APP_DIR"
    exit 1
fi

cd "$APP_DIR"

# Pull latest code
echo ""
echo "[1/5] Pulling latest code from GitHub..."
git pull origin main

# Install/update dependencies
echo ""
echo "[2/5] Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Verify .env exists
echo ""
echo "[3/5] Verifying configuration..."
if [ ! -f "$APP_DIR/.env" ]; then
    echo "WARNING: .env file not found! Create it from .env.production or configure manually."
    exit 1
fi

source_env

if [ -z "$DB_HOST" ] || [ -z "$DB_DATABASE" ]; then
    echo "WARNING: DB_HOST or DB_DATABASE not set in .env"
    exit 1
fi

if ! grep -q "APP_ENV=production" "$APP_DIR/.env"; then
    echo "WARNING: APP_ENV is not set to 'production' in .env"
fi

# Database operations
MYSQL_CMD="mysql -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE"

if [ "$FRESH_INSTALL" = true ]; then
    echo ""
    echo "[DB] Running fresh install..."
    mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    for f in database/migrations/*.sql; do
        echo "  Running: $f"
        $MYSQL_CMD < "$f"
    done
    echo "  Seeding admin user..."
    php database/seeds/seed.php
else
    echo ""
    echo "[DB] Checking for new migrations..."
    for f in database/migrations/*.sql; do
        echo "  Running: $f"
        $MYSQL_CMD < "$f" 2>/dev/null || true
    done
fi

# Ensure directories exist
echo ""
echo "[4/5] Setting up directories and permissions..."
mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/cache" "$APP_DIR/storage/sessions"
mkdir -p "$APP_DIR/public/uploads/scans" "$APP_DIR/public/uploads/ocr"

chown -R $WEBUSER:$WEBUSER "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/public/uploads"
chmod 600 "$APP_DIR/.env"

# Restart Apache if needed
echo ""
echo "[5/5] Reloading Apache..."
systemctl reload apache2

echo ""
echo "========================================="
echo "  Deploy complete!"
echo "========================================="
echo ""
echo "  Site: https://scraper.visionquest2020.net"
echo ""
