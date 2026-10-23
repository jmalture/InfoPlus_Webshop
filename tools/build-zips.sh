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
git -C "$ROOT" archive --format=zip --prefix="infoplus/" -o "$OUT/infoplus-theme.zip" "$REF:wp-content/themes/infoplus"
git -C "$ROOT" archive --format=zip --prefix="Infoplus Suite/" -o "$OUT/infoplus-suite.zip" "$REF:wp-content/plugins/infoplus-suite"
echo "Built from $(git -C "$ROOT" rev-parse --short "$REF"):"
ls -l "$OUT"/*.zip
