#!/bin/sh
# Nieuwe versie uitbrengen: bin/release.sh 3.3.1
# Zet het versienummer, draait de tests, commit, tagt en pusht. De sites
# zien de update daarna binnen 12 uur (of direct via "Opnieuw controleren").
set -e
cd "$(dirname "$0")/.."

version="$1"
if ! printf '%s' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
   echo "Gebruik: bin/release.sh X.Y.Z" >&2
   exit 1
fi
if [ -n "$(git status --porcelain)" ]; then
   echo "Werkmap is niet schoon; commit of stash eerst je wijzigingen." >&2
   exit 1
fi
if git rev-parse -q --verify "refs/tags/v$version" >/dev/null; then
   echo "Tag v$version bestaat al." >&2
   exit 1
fi

sed -i '' -E "s/^\* Version: .*/* Version: $version/" studiosambal-plugin.php
sed -i '' -E "s/define\( 'STUDIOSAMBAL_PLUGIN_VERSION', '[^']*' \)/define( 'STUDIOSAMBAL_PLUGIN_VERSION', '$version' )/" studiosambal-plugin.php
grep -q "Version: $version" studiosambal-plugin.php
grep -q "'STUDIOSAMBAL_PLUGIN_VERSION', '$version'" studiosambal-plugin.php

bin/test.sh

git add studiosambal-plugin.php
git commit -q -m "Versie $version"
git tag "v$version"
git push -q origin main "v$version"
echo "v$version gepubliceerd."
