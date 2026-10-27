#!/usr/bin/env bash
#
# Génère le zip installable du plugin dans ./release/
# Usage : ./release.command (ou double-clic dans le Finder)

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"
OUT_DIR="$PWD/release" bash bin/build-zip.sh