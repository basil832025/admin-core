#!/usr/bin/env bash
set -e

APP_DIR="/var/www/test_sevia_c_usr/data/www/test.sevia.com.ua"
FRONTEND_DIR="$APP_DIR/packages/frontend-sevia"

PACKAGE_BUILD_DIR="$FRONTEND_DIR/public/build/frontend-sevia"
PUBLIC_BUILD_DIR="$APP_DIR/public/build"
PUBLIC_FRONTEND_BUILD_DIR="$PUBLIC_BUILD_DIR/frontend-sevia"


echo "Deploy admin-core..."
cd "$APP_DIR"

git fetch origin
git reset --hard origin/main


echo "Deploy frontend-sevia..."

if [ ! -d "$FRONTEND_DIR/.git" ]; then
    mkdir -p "$APP_DIR/packages"

    git clone \
        https://github.com/basil832025/frontend-sevia.git \
        "$FRONTEND_DIR"
fi

cd "$FRONTEND_DIR"

git fetch origin
git reset --hard origin/master


echo "Publish frontend build..."

if [ ! -d "$PACKAGE_BUILD_DIR" ]; then
    echo "ERROR: Frontend build not found:"
    echo "$PACKAGE_BUILD_DIR"
    exit 1
fi

mkdir -p "$PUBLIC_BUILD_DIR"

# Удаляем предыдущий build проекта,
# чтобы не оставались старые hashed assets
rm -rf "$PUBLIC_FRONTEND_BUILD_DIR"

# Копируем актуальный build из package repo
cp -a "$PACKAGE_BUILD_DIR" "$PUBLIC_BUILD_DIR/"


echo "Clear Laravel cache..."
cd "$APP_DIR"

composer dump-autoload

php artisan optimize:clear
php artisan view:clear


echo "Deploy completed."
