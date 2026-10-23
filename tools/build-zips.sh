#!/usr/bin/env bash
# Builds the two upload ZIPs from a commit (default HEAD) into ./dist/.
#   infoplus-theme.zip   top folder "infoplus/"        -> Themes > Add > Upload
#   infoplus-suite.zip   top folder "Infoplus Suite/"  -> Plugins > Add > Upload
# Both are installed with "Replace current with uploaded". The Suite keeps the
# folder name "Infoplus Suite" because staging and production use it.
# Only committed files are packed: commit first.
set -euo pipefail
REF="${1:-HEAD}"
ROOT="$(git rev-parse --show-toplevel)"
OUT="$ROOT/dist"
mkdir -p "$OUT"
# The plugin header "Version:" and INFOPLUS_SUITE_VERSION must match, otherwise
# WordPress shows one release number and the asset cache busting uses another.
MAIN="$(git -C "$ROOT" show "$REF:wp-content/plugins/infoplus-suite/infoplus-suite.php")"
HEADER_VER="$(printf '%s\n' "$MAIN" | sed -n 's/^ \* Version: *\([0-9.]*\).*/\1/p' | head -1)"
CONST_VER="$(printf '%s\n' "$MAIN" | sed -n "s/^define('INFOPLUS_SUITE_VERSION', '\([0-9.]*\)');.*/\1/p" | head -1)"
if [ -z "$HEADER_VER" ] || [ "$HEADER_VER" != "$CONST_VER" ]; then
  echo "Suite version mismatch: header '$HEADER_VER', INFOPLUS_SUITE_VERSION '$CONST_VER'. Bump both in infoplus-suite.php." >&2
  exit 1
fi
git -C "$ROOT" archive --format=zip --prefix="infoplus/" -o "$OUT/infoplus-theme.zip" "$REF:wp-content/themes/infoplus"
git -C "$ROOT" archive --format=zip --prefix="Infoplus Suite/" -o "$OUT/infoplus-suite.zip" "$REF:wp-content/plugins/infoplus-suite"
echo "Built from $(git -C "$ROOT" rev-parse --short "$REF"), Suite $HEADER_VER:"
ls -l "$OUT"/*.zip
