#!/usr/bin/env bash
#
# First-time SVN checkout of the WordPress.org plugin repository.
# Creates a separate SVN working copy outside the main Git repo.
#
# Usage:
#   ./bin/wporg-svn-init.sh
#   SVN_DIR=~/Code/my-svn-dir ./bin/wporg-svn-init.sh
#
set -euo pipefail

# --- Configuration ---
SLUG="signals-dispatch-for-woocommerce"
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
SVN_DIR="${SVN_DIR:-$(dirname "$REPO_DIR")/${SLUG}-wporg-svn}"

echo "WordPress.org SVN Init"
echo "======================"
echo ""
echo "  Plugin slug: $SLUG"
echo "  SVN URL:     $SVN_URL"
echo "  Target dir:  $SVN_DIR"
echo ""

# --- Guard: already checked out ---
if [[ -d "$SVN_DIR/.svn" ]]; then
    echo "SVN working copy already exists at: $SVN_DIR"
    echo ""
    echo "To update it, run:"
    echo "  svn up \"$SVN_DIR\""
    exit 0
fi

# --- Guard: directory exists but isn't SVN ---
if [[ -d "$SVN_DIR" ]] && [[ ! -d "$SVN_DIR/.svn" ]]; then
    echo "Error: Directory exists but is not an SVN working copy: $SVN_DIR" >&2
    echo "Remove it manually and re-run this script." >&2
    exit 1
fi

# --- Checkout ---
echo "Checking out SVN repository (this may take a moment)..."
echo ""

svn checkout "$SVN_URL" "$SVN_DIR"

echo ""
echo "SVN working copy created at: $SVN_DIR"
echo ""
echo "Expected structure:"
echo "  $SVN_DIR/"
echo "  ├── trunk/     (current plugin code)"
echo "  ├── tags/      (release snapshots)"
echo "  └── assets/    (wp.org banners, icons, screenshots)"
echo ""
echo "Done. You can now use bin/wporg-release.sh to publish releases."
