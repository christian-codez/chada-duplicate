#!/usr/bin/env bash
# Build a distributable Chada Duplicate release ZIP and drop it into the
# License Manager's release storage so the auto-update endpoint can serve it.
#
# Output layout (on the License Manager host):
#   wp-content/uploads/clm-releases/chada-duplicate/<version>.zip
#   wp-content/uploads/clm-releases/chada-duplicate/manifest.json
#
# The version is read straight from the main plugin file's header. Chada
# Duplicate is a free product with NO JS build step (assets/js is plain JS), so
# there's nothing to compile — we just stage, zip, and install.
#
# Usage:
#   tools/build-release.sh                  # build, install to local uploads
#   tools/build-release.sh --no-install     # only emit the ZIP into ./dist
#   RELEASES_DIR=/path tools/build-release.sh
#     # override where the ZIP is dropped (e.g. point at a staging server)
#
# Requirements: bash, rsync, zip, awk.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_SLUG="chada-duplicate"
DEFAULT_RELEASES_DIR="/Users/christian/Server/forge/app/public/wp-content/uploads/clm-releases/${PLUGIN_SLUG}"
RELEASES_DIR="${RELEASES_DIR:-$DEFAULT_RELEASES_DIR}"

INSTALL=1
for arg in "$@"; do
	case "$arg" in
		--no-install) INSTALL=0 ;;
	esac
done

cd "$PLUGIN_DIR"

# --- 1. Version + name extraction -------------------------------------
VERSION="$(awk -F': *' '/^ \* Version: */ { print $2; exit }' "${PLUGIN_SLUG}.php" | tr -d '\r')"
if [[ -z "$VERSION" ]]; then
	echo "✗ Could not read Version from ${PLUGIN_SLUG}.php" >&2
	exit 1
fi
PLUGIN_NAME="Chada Duplicate"

echo "→ Building ${PLUGIN_NAME} ${VERSION}"

# --- 2. Stage + zip ----------------------------------------------------
DIST_DIR="${PLUGIN_DIR}/dist"
STAGE_DIR="${DIST_DIR}/${PLUGIN_SLUG}"
rm -rf "$DIST_DIR"
mkdir -p "$STAGE_DIR"

# Keep dev-only files out of the shipped ZIP. If a file is consumed only by a
# developer's toolchain or is internal project documentation, it doesn't belong
# in the merchant's wp-content/plugins/chada-duplicate/.
rsync -a \
	--exclude '.git/' \
	--exclude '.github/' \
	--exclude '.gitignore' \
	--exclude '.gitattributes' \
	\
	`# IDE / editor configuration` \
	--exclude '.idea/' \
	--exclude '.vscode/' \
	--exclude '.claude/' \
	--exclude '.editorconfig' \
	\
	`# Project dev docs & design handoff — internal only` \
	--exclude 'CLAUDE.md' \
	--exclude 'BUILD-PLAN.md' \
	--exclude 'DESIGN-BRIEF.md' \
	--exclude 'design_handoff_chada_clone/' \
	\
	`# Tooling, build outputs, previous releases` \
	--exclude 'tools/' \
	--exclude 'dist/' \
	--exclude 'node_modules/' \
	--exclude 'tests/' \
	--exclude 'composer.json' \
	--exclude 'composer.lock' \
	--exclude 'phpcs.xml*' \
	\
	`# Secrets (defensive — should never live here)` \
	--exclude '.env' \
	--exclude '.env.*' \
	\
	`# OS / editor scratch files` \
	--exclude '.DS_Store' \
	--exclude 'Thumbs.db' \
	--exclude '*.log' \
	--exclude '*.swp' \
	--exclude '*.bak' \
	--exclude '*~' \
	./ "$STAGE_DIR/"

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="${DIST_DIR}/${ZIP_NAME}"
( cd "$DIST_DIR" && zip -rq "$ZIP_NAME" "$PLUGIN_SLUG" )

SIZE_KB="$(du -k "$ZIP_PATH" | awk '{ print $1 }')"
echo "  ✓ ${ZIP_NAME} (${SIZE_KB} KB)"

# --- 3. Install to the License Manager release storage ----------------
if [[ "$INSTALL" -eq 1 ]]; then
	mkdir -p "$RELEASES_DIR"
	cp "$ZIP_PATH" "${RELEASES_DIR}/${VERSION}.zip"
	echo "  ✓ Installed → ${RELEASES_DIR}/${VERSION}.zip"

	# Manifest — surfaced by /update-check under the plugins_api shape, so any
	# field added here starts appearing in the WP "View details" modal.
	MANIFEST="${RELEASES_DIR}/manifest.json"
	cat > "$MANIFEST" <<EOF
{
	"slug": "${PLUGIN_SLUG}",
	"name": "${PLUGIN_NAME}",
	"version": "${VERSION}",
	"tested": "6.7",
	"requires": "6.0",
	"requires_php": "7.4",
	"author": "Chada Creatives",
	"homepage": "https://shop.chadacreatives.com/chada-duplicate/",
	"last_updated": "$(date -u +%Y-%m-%d)",
	"sections": {
		"description": "One-click duplicate / clone for posts, pages, custom post types, and WooCommerce products (including variations). Copies meta, taxonomies, the featured image, and full product data. Free.",
		"changelog": "See the Chada Duplicate documentation for the full changelog."
	}
}
EOF
	echo "  ✓ Updated manifest → ${MANIFEST}"
fi

echo ""
echo "Release built. To test the auto-update locally:"
echo "  1. Bump CHADA_DUP_VERSION + the plugin header to a higher number"
echo "  2. Re-run this script"
echo "  3. Visit wp-admin → Dashboard → Updates → Check Again"
