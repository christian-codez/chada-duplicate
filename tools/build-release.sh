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
# Quick start — once /wp-content/.deploy.env exists (FTP host/user/pass), a full
# production release is ONE command:
#
#   tools/build-release.sh --bump patch --deploy   # 0.1.1 -> 0.1.2, build, upload
#
# --bump may be patch | minor | major. Drop --bump to release the current header
# version; drop --deploy to publish only to the local License Manager for testing.
#
# Usage:
#   tools/build-release.sh                  # build, install to local uploads
#   tools/build-release.sh --bump patch     # bump version first, then build/install
#   tools/build-release.sh --no-install     # only emit the ZIP into ./dist
#   tools/build-release.sh --deploy         # build + upload to PRODUCTION over FTPS
#   RELEASES_DIR=/path tools/build-release.sh
#     # override where the ZIP is dropped (e.g. point at a staging server)
#
# Deploy config comes from the shared /wp-content/.deploy.env (gitignored) —
# FTP_HOST, FTP_USER, FTP_PASS, FTP_PORT, CLM_RELEASES_BASE, TESTED_WP — read by
# every plugin's release script. The remote dir is CLM_RELEASES_BASE/<slug>. If
# FTP_PASS is empty you're prompted at run time. Upload is explicit FTPS (TLS);
# the ZIP goes first, then manifest.json, so the manifest never advertises a
# version whose ZIP isn't on the server yet.
#
# Requirements: bash, rsync, zip, awk; curl for --deploy.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_SLUG="chada-duplicate"
PLUGIN_NAME="Chada Duplicate"

# Shared deploy config at the wp-content root (gitignored): FTP_HOST, FTP_USER,
# FTP_PASS, FTP_PORT, CLM_RELEASES_BASE, TESTED_WP. Read by every plugin's
# release script so credentials live in ONE place. Explicit env vars still win.
WPCONTENT_DIR="$(cd "$PLUGIN_DIR/../.." && pwd)"
if [ -f "${WPCONTENT_DIR}/.deploy.env" ]; then
	set -a; . "${WPCONTENT_DIR}/.deploy.env"; set +a
fi

# WordPress version this release is tested against (stamped into the manifest +
# readme.txt so "Tested up to" never lags a stale value → WP's "not tested"
# warning). The shared .deploy.env sets it; this is the per-plugin fallback.
TESTED_WP="${TESTED_WP:-7.0}"

# Default LOCAL target: the License Manager release storage under uploads/.
DEFAULT_RELEASES_DIR="${WPCONTENT_DIR}/uploads/clm-releases/${PLUGIN_SLUG}"
RELEASES_DIR="${RELEASES_DIR:-$DEFAULT_RELEASES_DIR}"

INSTALL=1
DEPLOY=0
BUMP=""
while [ "$#" -gt 0 ]; do
	case "$1" in
		--no-install) INSTALL=0 ;;
		--deploy)     DEPLOY=1 ;;
		--bump)       BUMP="${2:-}"; shift ;;
		--bump=*)     BUMP="${1#*=}" ;;
		*) echo "Unknown option: $1" >&2; exit 1 ;;
	esac
	shift
done

cd "$PLUGIN_DIR"

# Clean up the temp build dir, and — if a --bump ran but we exit before
# publishing (e.g. the upload fails) — roll the version header back, so a failed
# retry never inflates the version.
DIST_DIR="${PLUGIN_DIR}/dist"
BUMP_REVERT=""
cleanup() {
	local rc=$?
	rm -rf "$DIST_DIR"
	if [ "$rc" -ne 0 ] && [ -n "$BUMP_REVERT" ]; then
		sed -i '' -E "s/^( \* Version:[[:space:]]*).*/\1${BUMP_REVERT}/" "${PLUGIN_DIR}/${PLUGIN_SLUG}.php"
		echo "↩  Did not publish — reverted version back to ${BUMP_REVERT}." >&2
	fi
}
trap cleanup EXIT

# --- 0. Optional version bump (--bump patch|minor|major) ---------------
# Edits the one `Version:` header line; the CHADA_DUP_VERSION constant follows.
if [ -n "$BUMP" ]; then
	CUR="$(grep -m1 -E '^\s*\*\s*Version:' "${PLUGIN_SLUG}.php" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//')"
	if [[ "$CUR" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
		MA="${BASH_REMATCH[1]}"; MI="${BASH_REMATCH[2]}"; PA="${BASH_REMATCH[3]}"
		case "$BUMP" in
			patch) PA=$(( PA + 1 )) ;;
			minor) MI=$(( MI + 1 )); PA=0 ;;
			major) MA=$(( MA + 1 )); MI=0; PA=0 ;;
			*) echo "✗ --bump must be patch, minor, or major (got '$BUMP')" >&2; exit 1 ;;
		esac
		NEWV="${MA}.${MI}.${PA}"
		sed -i '' -E "s/^( \* Version:[[:space:]]*).*/\1${NEWV}/" "${PLUGIN_SLUG}.php"
		BUMP_REVERT="$CUR"   # so cleanup() can undo this if we fail before publishing
		echo "→ Bumped version ${CUR} → ${NEWV}"
	else
		echo "✗ Version '${CUR}' is not X.Y.Z — bump it by hand, then re-run without --bump" >&2
		exit 1
	fi
fi

# --- 1. Version + name extraction -------------------------------------
VERSION="$(awk -F': *' '/^ \* Version: */ { print $2; exit }' "${PLUGIN_SLUG}.php" | tr -d '\r')"
if [[ -z "$VERSION" ]]; then
	echo "✗ Could not read Version from ${PLUGIN_SLUG}.php" >&2
	exit 1
fi

echo "→ Building ${PLUGIN_NAME} ${VERSION}"

# Keep readme.txt in step with this release — one source of truth, no hand-edits.
# (Chada Duplicate ships no readme.txt, so this is a no-op; kept for consistency.)
if [ -f readme.txt ]; then
	sed -i '' -E "s/^(Stable tag:[[:space:]]*).*/\1${VERSION}/" readme.txt
	sed -i '' -E "s/^(Tested up to:[[:space:]]*).*/\1${TESTED_WP}/" readme.txt
fi

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

# --no-install with no --deploy: emit the ZIP into ./dist and stop.
if [[ "$INSTALL" -eq 0 && "$DEPLOY" -eq 0 ]]; then
	echo "  ✓ ZIP only (--no-install) → ${ZIP_PATH}"
	trap - EXIT   # keep dist/ so the emitted ZIP survives
	exit 0
fi

# --- 3. Manifest -------------------------------------------------------
# Surfaced by /update-check under the plugins_api shape, so any field added here
# starts appearing in the WP "View details" modal. The release ZIP filename MUST
# equal "version" here — the endpoint pairs them by that value.
MANIFEST_SRC="${DIST_DIR}/manifest.json"
cat > "$MANIFEST_SRC" <<EOF
{
	"slug": "${PLUGIN_SLUG}",
	"name": "${PLUGIN_NAME}",
	"version": "${VERSION}",
	"tested": "${TESTED_WP}",
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

# --- 4. Publish: upload to production over FTPS, or copy to a local CLM -
if [[ "$DEPLOY" -eq 1 ]]; then
	# Explicit FTPS (port 21) upload via curl. Connection comes from the shared
	# /wp-content/.deploy.env; the password is read at run time if FTP_PASS isn't
	# set there, and is never written to disk by this script.
	: "${FTP_HOST:?set FTP_HOST in /wp-content/.deploy.env}"
	: "${FTP_USER:?set FTP_USER in /wp-content/.deploy.env}"
	FTP_PORT="${FTP_PORT:-21}"
	FTP_DIR="${CLM_RELEASES_BASE:-wp-content/uploads/clm-releases}/${PLUGIN_SLUG}"
	if [ -z "${FTP_PASS:-}" ]; then
		read -rsp "FTP password for ${FTP_USER}: " FTP_PASS
		echo
	fi
	BASE="ftp://${FTP_HOST}:${FTP_PORT}/${FTP_DIR#/}"
	echo "→ Uploading over explicit FTPS to ${FTP_HOST}:${FTP_PORT}/${FTP_DIR#/}"
	# --ssl-reqd forces explicit FTPS (TLS upgrade on the control channel);
	# --ftp-create-dirs makes any missing path components. ZIP first, then
	# manifest — so the manifest never points at a ZIP that isn't there yet.
	curl -fsS --ssl-reqd --ftp-create-dirs -u "${FTP_USER}:${FTP_PASS}" \
		-T "$ZIP_PATH" "${BASE}/${VERSION}.zip"
	curl -fsS --ssl-reqd --ftp-create-dirs -u "${FTP_USER}:${FTP_PASS}" \
		-T "$MANIFEST_SRC" "${BASE}/manifest.json"
	echo "  ✓ Uploaded ${VERSION}.zip + manifest.json"
	echo ""
	echo "Live. Customer sites see ${VERSION} on their next update-check"
	echo "(within ~12h, or instantly via Dashboard → Updates → \"Check again\")."
	exit 0
fi

# Local publish (default).
mkdir -p "$RELEASES_DIR"
cp "$ZIP_PATH"      "${RELEASES_DIR}/${VERSION}.zip"
cp "$MANIFEST_SRC"  "${RELEASES_DIR}/manifest.json"
echo "  ✓ Installed → ${RELEASES_DIR}/${VERSION}.zip"
echo "  ✓ Updated manifest → ${RELEASES_DIR}/manifest.json"

echo ""
echo "Release built. To test the auto-update locally:"
echo "  1. Bump the plugin header (or pass --bump patch) to a higher number"
echo "  2. Re-run this script"
echo "  3. Visit wp-admin → Dashboard → Updates → Check Again"
echo "Ship the same build to customers with:  tools/build-release.sh --deploy"
