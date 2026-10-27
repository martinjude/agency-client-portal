#!/usr/bin/env bash
#
# Construit l'archive distribuable : dist/agency-client-portal-<version>.zip
# Fichiers exclus : voir .distignore.
# Utilisé aussi par .github/workflows/release.yml pour chaque tag vX.Y.Z (GitHub Release).

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="agency-client-portal"
VERSION="$(sed -n 's/^[ *]*Version:[[:space:]]*//p' "$PLUGIN_DIR/agency.php" | head -1 | tr -d '[:space:]')"

if [ -z "$VERSION" ]; then
	echo "Version not found in agency.php" >&2
	exit 1
fi

STABLE_TAG="$(sed -n 's/^Stable tag:[[:space:]]*//p' "$PLUGIN_DIR/readme.txt" | tr -d '[:space:]')"
if [ "$STABLE_TAG" != "$VERSION" ]; then
	echo "Warning: readme.txt Stable tag ($STABLE_TAG) differs from agency.php Version ($VERSION)." >&2
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

rsync -a --exclude-from="$PLUGIN_DIR/.distignore" "$PLUGIN_DIR/" "$TMP_DIR/$SLUG/"

mkdir -p "$PLUGIN_DIR/dist"
ZIP="$PLUGIN_DIR/dist/$SLUG-$VERSION.zip"
rm -f "$ZIP"
(cd "$TMP_DIR" && zip -qr -X "$ZIP" "$SLUG")

echo "Built $ZIP ($(du -h "$ZIP" | cut -f1), $(unzip -l "$ZIP" | tail -1 | awk '{print $2}') files)"
