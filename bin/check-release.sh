#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="signals-dispatch-for-woocommerce"
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
VERSION=$(sed -n 's/^ \* Version: //p' "$PLUGIN_DIR/$PLUGIN_SLUG.php" | tr -d '[:space:]')
CONSTANT_VERSION=$(sed -n "s/^define( 'TMASD_VERSION', '\([^']*\)' );/\1/p" "$PLUGIN_DIR/$PLUGIN_SLUG.php")
STABLE_TAG=$(sed -n 's/^Stable tag: //p' "$PLUGIN_DIR/readme.txt" | tr -d '[:space:]')

if [[ -z "$VERSION" || "$VERSION" != "$CONSTANT_VERSION" || "$VERSION" != "$STABLE_TAG" ]]; then
    echo "Error: plugin header, TMASD_VERSION, and readme stable tag must match." >&2
    exit 1
fi

ZIP_PATH="$PLUGIN_DIR/$PLUGIN_SLUG-$VERSION.zip"
unzip -tq "$ZIP_PATH"
CONTENTS=$(unzip -Z1 "$ZIP_PATH")

for REQUIRED in "$PLUGIN_SLUG.php" readme.txt license.txt vendor/autoload.php; do
    if ! grep -Fxq "$PLUGIN_SLUG/$REQUIRED" <<< "$CONTENTS"; then
        echo "Error: package is missing $REQUIRED." >&2
        exit 1
    fi
done

PACKAGED_HEADER=$(unzip -p "$ZIP_PATH" "$PLUGIN_SLUG/$PLUGIN_SLUG.php")
PACKAGED_VERSION=$(sed -n 's/^ \* Version: //p' <<< "$PACKAGED_HEADER" | tr -d '[:space:]')
PACKAGED_CONSTANT=$(sed -n "s/^define( 'TMASD_VERSION', '\([^']*\)' );/\1/p" <<< "$PACKAGED_HEADER")
PACKAGED_STABLE_TAG=$(unzip -p "$ZIP_PATH" "$PLUGIN_SLUG/readme.txt" | sed -n 's/^Stable tag: //p' | tr -d '[:space:]')

if [[ "$VERSION" != "$PACKAGED_VERSION" || "$VERSION" != "$PACKAGED_CONSTANT" || "$VERSION" != "$PACKAGED_STABLE_TAG" ]]; then
    echo "Error: packaged versions do not match the requested release; commit version changes before building." >&2
    exit 1
fi

if grep -Eq "(^|/)(\.github|\.git|tests|bin|docs|node_modules)/|(^|/)(composer\.(json|lock)|phpunit\.xml(\.dist)?|phpcs\.xml\.dist|\.env([^/]*)?|.*\.(zip|log))$|/vendor/(phpunit|sebastian|squizlabs|wp-coding-standards)/" <<< "$CONTENTS"; then
    echo "Error: package contains development tooling, local files, or nested archives." >&2
    exit 1
fi

if grep -Ev "^$PLUGIN_SLUG/" <<< "$CONTENTS"; then
    echo "Error: all package files must be inside $PLUGIN_SLUG/." >&2
    exit 1
fi

CHECK_DIR=$(mktemp -d)
trap 'rm -rf "$CHECK_DIR"' EXIT
unzip -q "$ZIP_PATH" -d "$CHECK_DIR"
php -r '
    define("ABSPATH", $argv[1] . "/");
    require $argv[1] . "/vendor/autoload.php";
    foreach (array(
        "TMASD\\Signals\\Dispatch\\Queue\\QueueService",
        "TMASD\\Signals\\Dispatch\\API\\WebhookController",
        "TMASD\\Signals\\Dispatch\\Core\\Container"
    ) as $class) {
        if (!class_exists($class)) {
            throw new RuntimeException("Release autoloader cannot load " . $class);
        }
    }
    echo "Production autoloader validated.\n";
' "$CHECK_DIR/$PLUGIN_SLUG"

echo "Release package validated: $PLUGIN_SLUG-$VERSION.zip"
