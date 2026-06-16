#!/usr/bin/env bash
# Rebuild the i18n catalog in one step:
#   1. languages/chada-duplicate.pot   (template, from PHP + assets/js)
#   2. languages/*.mo                  (compiled from the .po files)
#   3. languages/*-<md5>.json          (block-editor JS strings)
#
# Run this after changing any translatable string. Adding NEW translations is
# still manual: new msgids show up in the .pot, so fill them in each
# languages/chada-duplicate-{de_DE,es_ES,fr_FR}.po and re-run this script.
#
# Requires wp-cli. The i18n sub-commands scan files only — no database needed.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="chada-duplicate"
cd "$PLUGIN_DIR"

# Resolve the wp-cli binary ONCE (before defining the wrapper, so `command -v`
# finds the real phar and not the function below).
WP_BIN="$(command -v wp || true)"
if [[ -z "$WP_BIN" ]]; then
	echo "✗ wp-cli not found on PATH. Install it: https://wp-cli.org/" >&2
	exit 1
fi

# Wrapper that silences the PHP 8 deprecation noise some wp-cli/PHP combos emit.
wp() { php -d error_reporting=0 -d display_errors=0 "$WP_BIN" "$@"; }

echo "→ make-pot"
wp i18n make-pot . "languages/${SLUG}.pot" \
	--slug="$SLUG" --domain="$SLUG" \
	--exclude=tools,design_handoff_chada_clone,dist

echo "→ make-mo"
wp i18n make-mo languages/ languages/

echo "→ make-json (block-editor strings)"
wp i18n make-json languages/ --no-purge --pretty-print

echo ""
echo "✓ i18n catalog rebuilt (.pot, .mo, .json)."
echo "  New source strings land in languages/${SLUG}.pot — translate them in"
echo "  languages/${SLUG}-{de_DE,es_ES,fr_FR}.po, then re-run this script."
