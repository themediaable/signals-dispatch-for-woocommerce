#!/usr/bin/env bash
#
# WordPress.org SVN release script for Signals Dispatch for WooCommerce.
#
# Builds a clean release directory from Git, syncs it to the SVN trunk,
# optionally syncs wp.org assets, handles SVN add/remove, tags the release,
# and prints the final commit command for manual execution.
#
# Usage:
#   ./bin/wporg-release.sh              # reads version from plugin header
#   ./bin/wporg-release.sh 1.2.0        # explicit version
#   VERSION=1.2.0 ./bin/wporg-release.sh
#
# Environment variable overrides:
#   SVN_DIR      Path to SVN working copy (default: sibling of Git repo)
#   DIST_DIR     Path to build output      (default: <repo>/dist/<slug>)
#   DRY_RUN=1    Show what would happen without modifying SVN
#
set -euo pipefail

# =============================================================================
# Configuration
# =============================================================================
SLUG="signals-dispatch-for-woocommerce"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DIST_DIR="${DIST_DIR:-${REPO_DIR}/dist/${SLUG}}"
SVN_DIR="${SVN_DIR:-$(dirname "$REPO_DIR")/${SLUG}-wporg-svn}"
WPORG_ASSETS_DIR="${REPO_DIR}/.wordpress-org"
DRY_RUN="${DRY_RUN:-0}"

# =============================================================================
# Helpers
# =============================================================================
die()     { echo "Error: $*" >&2; exit 1; }
info()    { echo "  $*"; }
header()  { echo ""; echo "--- $* ---"; }
success() { echo "  $* ✔"; }

confirm() {
    if [[ "$DRY_RUN" == "1" ]]; then return 0; fi
    local prompt="$1"
    read -r -p "$prompt [y/N] " answer
    [[ "$answer" =~ ^[Yy]$ ]] || die "Aborted by user."
}

# =============================================================================
# Resolve version
# =============================================================================
if [[ -n "${1:-}" ]]; then
    VERSION="$1"
elif [[ -n "${VERSION:-}" ]]; then
    : # already set via env
else
    VERSION=$(grep -m1 'Version:' "$REPO_DIR/$SLUG.php" \
        | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
fi

[[ -n "$VERSION" ]] || die "Could not determine plugin version. Pass it as an argument or set VERSION."

# =============================================================================
# Banner
# =============================================================================
echo "============================================"
echo "  WordPress.org Release: $SLUG"
echo "  Version: $VERSION"
[[ "$DRY_RUN" == "1" ]] && echo "  ** DRY RUN — no SVN changes will be made **"
echo "============================================"
echo ""
echo "  Git repo:       $REPO_DIR"
echo "  Dist dir:       $DIST_DIR"
echo "  SVN dir:        $SVN_DIR"
echo "  WP.org assets:  $WPORG_ASSETS_DIR"

# =============================================================================
# Pre-flight checks
# =============================================================================
header "Pre-flight checks"

# SVN working copy
[[ -d "$SVN_DIR/.svn" ]] \
    || die "SVN working copy not found at $SVN_DIR. Run bin/wporg-svn-init.sh first."
success "SVN working copy found"

# Git repo
git -C "$REPO_DIR" rev-parse --git-dir > /dev/null 2>&1 \
    || die "Not a Git repository: $REPO_DIR"
success "Git repository detected"

# Uncommitted changes warning
if ! git -C "$REPO_DIR" diff --quiet HEAD 2>/dev/null; then
    echo ""
    echo "  WARNING: You have uncommitted Git changes."
    confirm "  Continue anyway?"
fi

# Plugin header version
HEADER_VERSION=$(grep -m1 'Version:' "$REPO_DIR/$SLUG.php" \
    | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
[[ "$HEADER_VERSION" == "$VERSION" ]] \
    || die "Plugin header version ($HEADER_VERSION) != release version ($VERSION)."
success "Plugin header version: $HEADER_VERSION"

# Stable Tag in readme.txt
STABLE_TAG=$(grep -im1 'Stable tag:' "$REPO_DIR/readme.txt" \
    | sed 's/.*Stable tag:[[:space:]]*//' | tr -d '[:space:]')
[[ "$STABLE_TAG" == "$VERSION" ]] \
    || die "readme.txt Stable tag ($STABLE_TAG) != release version ($VERSION)."
success "Stable tag: $STABLE_TAG"

# TMASD_VERSION constant
CONST_VERSION=$(grep -m1 "TMASD_VERSION" "$REPO_DIR/$SLUG.php" \
    | sed "s/.*'TMASD_VERSION',[[:space:]]*'//" | sed "s/'.*//" | tr -d '[:space:]')
if [[ -n "$CONST_VERSION" && "$CONST_VERSION" != "$VERSION" ]]; then
    die "TMASD_VERSION constant ($CONST_VERSION) != release version ($VERSION)."
fi
success "TMASD_VERSION constant: $CONST_VERSION"

# Tested up to (informational)
TESTED_UPTO=$(grep -im1 'Tested up to:' "$REPO_DIR/readme.txt" \
    | sed 's/.*Tested up to:[[:space:]]*//' | tr -d '[:space:]')
info "Tested up to: $TESTED_UPTO (verify this is current)"

# Tag collision
if [[ -d "$SVN_DIR/tags/$VERSION" ]]; then
    die "SVN tag $VERSION already exists. Bump the version before releasing."
fi
success "SVN tag $VERSION does not exist yet"

# Main plugin file present
[[ -f "$REPO_DIR/$SLUG.php" ]] || die "Main plugin file not found: $SLUG.php"

# readme.txt present
[[ -f "$REPO_DIR/readme.txt" ]] || die "readme.txt not found in repo root."

echo ""
echo "  All pre-flight checks passed."

# =============================================================================
# Step 1: Build clean release directory
# =============================================================================
header "Step 1: Build clean release directory"

rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"

# Export tracked files (respects .gitattributes export-ignore)
info "Exporting Git archive..."
git -C "$REPO_DIR" archive --format=tar HEAD \
    | tar -xf - -C "$DIST_DIR"

# Install production Composer dependencies
if [[ -f "$REPO_DIR/composer.json" ]]; then
    info "Installing production Composer dependencies..."
    cp "$REPO_DIR/composer.json" "$DIST_DIR/"
    [[ -f "$REPO_DIR/composer.lock" ]] && cp "$REPO_DIR/composer.lock" "$DIST_DIR/"

    composer install \
        --working-dir="$DIST_DIR" \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --quiet

    # Remove Composer manifests from release
    rm -f "$DIST_DIR/composer.json" "$DIST_DIR/composer.lock"
    success "Composer dependencies installed (no-dev)"
fi

# Scrub any stray junk files
find "$DIST_DIR" -name '.DS_Store' -delete 2>/dev/null || true
find "$DIST_DIR" -name '._*' -delete 2>/dev/null || true
find "$DIST_DIR" -name '__MACOSX' -type d -exec rm -rf {} + 2>/dev/null || true
find "$DIST_DIR" -name 'Thumbs.db' -delete 2>/dev/null || true

FILE_COUNT=$(find "$DIST_DIR" -type f | wc -l | xargs)
success "Build complete: $FILE_COUNT files in dist"

# Quick sanity: main plugin file at the root of dist, not nested
if [[ ! -f "$DIST_DIR/$SLUG.php" ]]; then
    die "Main plugin file ($SLUG.php) not at root of dist. Check your build."
fi
success "Main plugin file at dist root"

# Check for obvious secrets
if grep -rqE '(AKIA[0-9A-Z]{16}|sk_live_|password\s*=\s*["\x27][^\x27"]+)' "$DIST_DIR" 2>/dev/null; then
    die "Possible secrets detected in dist output! Aborting."
fi
success "No obvious secrets detected"

if [[ "$DRY_RUN" == "1" ]]; then
    echo ""
    echo "  DRY RUN: Skipping SVN operations. Build output is at:"
    echo "  $DIST_DIR"
    exit 0
fi

# =============================================================================
# Step 2: Update SVN working copy
# =============================================================================
header "Step 2: Update SVN working copy"

svn up "$SVN_DIR"
success "SVN working copy updated"

# =============================================================================
# Step 3: Sync release to SVN trunk
# =============================================================================
header "Step 3: Sync release to SVN trunk"

rsync -a --delete \
    --exclude='.svn' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    "$DIST_DIR/" "$SVN_DIR/trunk/"

success "Synced dist -> trunk"

# =============================================================================
# Step 4: Sync WordPress.org assets (banners, icons, screenshots)
# =============================================================================
header "Step 4: Sync WordPress.org assets"

if [[ -d "$WPORG_ASSETS_DIR" ]] && [[ -n "$(ls -A "$WPORG_ASSETS_DIR" 2>/dev/null)" ]]; then
    mkdir -p "$SVN_DIR/assets"
    rsync -a --delete \
        --exclude='.svn' \
        --exclude='.DS_Store' \
        --exclude='README.md' \
        "$WPORG_ASSETS_DIR/" "$SVN_DIR/assets/"
    success "Synced .wordpress-org -> SVN assets"
else
    info "No wp.org assets found at $WPORG_ASSETS_DIR (skipping)"
    info "Add banner/icon/screenshot images there for future releases"
fi

# =============================================================================
# Step 5: SVN add / remove
# =============================================================================
header "Step 5: SVN add / remove"

# Add all unversioned files in trunk and assets
svn status "$SVN_DIR" | grep '^?' | awk '{print $2}' | while IFS= read -r filepath; do
    svn add --parents "$filepath"
done || true
ADDED=$(svn status "$SVN_DIR" | grep -c '^A' || true)
info "Files staged for addition: $ADDED"

# Remove all missing files
svn status "$SVN_DIR" | grep '^!' | awk '{print $2}' | while IFS= read -r filepath; do
    svn rm "$filepath"
done || true
REMOVED=$(svn status "$SVN_DIR" | grep -c '^D' || true)
info "Files staged for removal: $REMOVED"

# =============================================================================
# Step 6: Create SVN tag
# =============================================================================
header "Step 6: Create SVN tag $VERSION"

svn cp "$SVN_DIR/trunk" "$SVN_DIR/tags/$VERSION"
success "Created tags/$VERSION"

# =============================================================================
# Step 7: Final SVN status
# =============================================================================
header "Step 7: SVN status (review carefully)"
echo ""
svn status "$SVN_DIR"
echo ""

# Count changes
TOTAL_CHANGES=$(svn status "$SVN_DIR" | grep -c '^[ADMR]' || true)
info "Total staged changes: $TOTAL_CHANGES"

# =============================================================================
# Step 8: Commit instructions
# =============================================================================
echo ""
echo "============================================"
echo "  Ready to publish v$VERSION"
echo "============================================"
echo ""
echo "  Review the SVN status above, then commit with:"
echo ""
echo "    cd \"$SVN_DIR\" && svn commit -m \"Release v$VERSION\" --username themediaable"
echo ""
echo "  After commit, verify at:"
echo "    https://wordpress.org/plugins/$SLUG/"
echo ""
echo "  To REVERT everything if something looks wrong:"
echo "    svn revert -R \"$SVN_DIR\" && svn up \"$SVN_DIR\""
echo ""
