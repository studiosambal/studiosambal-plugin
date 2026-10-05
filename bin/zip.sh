#!/bin/sh
# Installatie-zip met de juiste mapnaam, van de laatste tag (of een opgegeven ref).
set -e
cd "$(dirname "$0")/.."
ref="${1:-$(git describe --tags --abbrev=0)}"
git archive --format=zip --prefix=studiosambal-plugin/ -o studiosambal-plugin.zip "$ref"
echo "studiosambal-plugin.zip ($ref)"
