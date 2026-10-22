#!/usr/bin/env bash
#
# Builds a clean, CodeCanyon-ready release zip from this repository.
#
# Never runs in place: everything happens in a throwaway copy under
# packaging/build/, so this script cannot damage a working dev checkout
# (its own vendor/, .env, database, git history, etc. are left untouched).
#
# Produces two zips in packaging/dist/:
#   daftari-vX.Y.Z-full.zip     — application source + a production vendor/
#                                 (composer install --no-dev) + compiled
#                                 assets, for buyers on shared hosting with
#                                 no SSH/Composer access. This is the
#                                 default artifact most CodeCanyon Laravel
#                                 buyers expect.
#   daftari-vX.Y.Z-source.zip   — application source only, no vendor/ or
#                                 node_modules — for buyers who will run
#                                 `composer install` and `npm run build`
#                                 themselves.
#
# Usage:
#   packaging/build-package.sh [version]
#
# Requires: rsync, zip, php, composer. npm/node only if you want the
# -full zip's public/build assets freshly recompiled instead of reusing
# whatever is already committed/built in this checkout.

set -euo pipefail

VERSION="${1:-$(date +%Y.%m.%d)}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$ROOT_DIR/packaging/build"
DIST_DIR="$ROOT_DIR/packaging/dist"
STAGE="$BUILD_DIR/daftari"

echo "==> Building Daftari package v$VERSION"
rm -rf "$BUILD_DIR" "$DIST_DIR"
mkdir -p "$STAGE" "$DIST_DIR"

# ---------------------------------------------------------------------
# 1. Copy application source, excluding everything that must never ship
#    (see docs/codecanyon/PACKAGING.md for the reasoning behind each
#    line — this list is the authoritative "do not include" checklist).
# ---------------------------------------------------------------------
rsync -a "$ROOT_DIR/" "$STAGE/" \
    --exclude ".git" \
    --exclude ".github" \
    --exclude "packaging" \
    --exclude "node_modules" \
    --exclude "vendor" \
    --exclude ".env" \
    --exclude ".env.backup" \
    --exclude ".env.production" \
    --exclude ".env.testing" \
    --exclude "database/*.sqlite" \
    --exclude ".phpunit.result.cache" \
    --exclude ".phpunit.cache" \
    --exclude "phpunit.xml" \
    --exclude "tests" \
    --exclude "docs/module-*-testing.md" \
    --exclude "storage/logs/*.log" \
    --exclude "storage/framework/cache/data/*" \
    --exclude "storage/framework/sessions/*" \
    --exclude "storage/framework/views/*" \
    --exclude "storage/framework/testing/*" \
    --exclude "storage/app/public/*" \
    --exclude "public/storage" \
    --exclude "public/build" \
    --exclude ".idea" \
    --exclude ".vscode" \
    --exclude ".fleet" \
    --exclude ".nova" \
    --exclude ".zed" \
    --exclude ".DS_Store"

# Re-create the empty, gitignored placeholder directories Laravel expects
# to exist at boot (storage/framework/{cache,sessions,views}, etc.) —
# rsync's excludes above stripped their contents but Laravel still needs
# the directories themselves to be present and writable.
for d in storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing storage/app/public storage/logs; do
    mkdir -p "$STAGE/$d"
    touch "$STAGE/$d/.gitignore"
done

# .env.example ships (it's the template); nothing derived from the real
# .env ever does.
test -f "$STAGE/.env.example"

# ---------------------------------------------------------------------
# 2. Source-only zip (no vendor/, no node_modules — buyer runs composer
#    install / npm run build themselves per the documentation).
# ---------------------------------------------------------------------
echo "==> Packaging source-only zip"
(cd "$BUILD_DIR" && zip -rq "$DIST_DIR/daftari-v$VERSION-source.zip" "daftari")

# ---------------------------------------------------------------------
# 3. Full zip: production vendor/ (composer install --no-dev, so
#    require-dev packages like Pail/Pint/Sail/PHPUnit never ship) and
#    freshly compiled front-end assets, for buyers without CLI access.
# ---------------------------------------------------------------------
echo "==> Installing production dependencies into the staged copy"
(cd "$STAGE" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

if command -v npm >/dev/null 2>&1; then
    echo "==> Building front-end assets"
    (cd "$STAGE" && npm ci --silent && npm run build --silent && rm -rf node_modules)
else
    echo "==> npm not found — skipping asset rebuild; reusing public/build if already present"
fi

echo "==> Packaging full zip"
(cd "$BUILD_DIR" && zip -rq "$DIST_DIR/daftari-v$VERSION-full.zip" "daftari")

echo "==> Done. Artifacts:"
ls -lh "$DIST_DIR"
