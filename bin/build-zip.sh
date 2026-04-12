#!/usr/bin/env bash
#
# Build a production-ready ZIP for WordPress.org submission.
#
# Usage:
#   ./bin/build-zip.sh          # uses version from main plugin file
#   ./bin/build-zip.sh 1.2.0    # override version in filename
#
# Output: signals-dispatch-for-woocommerce-<version>.zip in project root
#
set -euo pipefail

PLUGIN_SLUG="signals-dispatch-for-woocommerce"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Resolve version from argument or plugin header.
if [[ -n "${1:-}" ]]; then
    VERSION="$1"
else
    VERSION=$(grep -m1 'Version:' "$PLUGIN_DIR/$PLUGIN_SLUG.php" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
fi

if [[ -z "$VERSION" ]]; then
    echo "Error: could not determine plugin version." >&2
    exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
BUILD_DIR=$(mktemp -d)

echo "Building $ZIP_NAME …"

# 1. Export tracked files (respects .gitattributes export-ignore).
git -C "$PLUGIN_DIR" archive --format=tar --prefix="$PLUGIN_SLUG/" HEAD \
    | tar -xf - -C "$BUILD_DIR"

# 2. Copy Composer files (excluded by export-ignore but needed for install).
cp "$PLUGIN_DIR/composer.json" "$BUILD_DIR/$PLUGIN_SLUG/"
[[ -f "$PLUGIN_DIR/composer.lock" ]] && cp "$PLUGIN_DIR/composer.lock" "$BUILD_DIR/$PLUGIN_SLUG/"

# 3. Install production-only Composer dependencies (autoloader + no dev).
composer install \
    --working-dir="$BUILD_DIR/$PLUGIN_SLUG" \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --quiet

# 4. Remove Composer files that aren't needed at runtime.
rm -f "$BUILD_DIR/$PLUGIN_SLUG/composer.json" \
      "$BUILD_DIR/$PLUGIN_SLUG/composer.lock"

# 4. Create ZIP.
(cd "$BUILD_DIR" && zip -rq "$PLUGIN_DIR/$ZIP_NAME" "$PLUGIN_SLUG")

# 5. Clean up.
rm -rf "$BUILD_DIR"

echo "✔ Created $ZIP_NAME ($(du -h "$PLUGIN_DIR/$ZIP_NAME" | cut -f1 | xargs))"
