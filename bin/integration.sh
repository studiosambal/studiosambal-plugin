#!/bin/sh
# Integratietest op een lokale WordPress-site (standaard ~/Sites/stichtingkego):
# installeren uit zip, mu-loader plaatsen en herstellen, melding als dat niet
# lukt, draaien na deactiveren, en of de updatecheck de laatste tag van GitHub ziet.
# De site houdt daarna de geteste versie (werkmap) geïnstalleerd.
set -e -o pipefail
cd "$(dirname "$0")/.."
repo="$(pwd)"
site="${WP_SITE:-$HOME/Sites/stichtingkego}"
plugin="$site/wp-content/plugins/studiosambal-plugin"
loader="$site/wp-content/mu-plugins/studiosambal-loader.php"
zip="$(mktemp -d)/studiosambal-plugin.zip"
# wp-cli (phar) print op nieuwere PHP een deprecation op stdout; die hoort niet in de uitvoer.
wpc() { wp --path="$site" "$@" 2>/dev/null | sed '/^Deprecated: /d; /^$/d'; }
fail() { echo "FOUT: $1" >&2; exit 1; }
ok() { echo "ok  $1"; }

# Zip van de werkmap, inclusief niet-gecommitte wijzigingen.
ref="$(git stash create)"
git archive --format=zip --prefix=studiosambal-plugin/ -o "$zip" "${ref:-HEAD}"
version="$(sed -nE "s/^\* Version: (.*)/\1/p" studiosambal-plugin.php)"

# Verse activatie nabootsen: een al actieve plugin wordt niet opnieuw geactiveerd.
rm -f "$loader"
wpc plugin deactivate studiosambal-plugin >/dev/null || true
wpc plugin install "$zip" --force --activate >/dev/null
[ "$(wpc plugin get studiosambal-plugin --field=version)" = "$version" ] || fail "versie na installatie"
ok "installatie uit zip ($version)"
[ -f "$loader" ] || fail "loader niet geplaatst bij activeren"
ok "loader geplaatst bij activeren"

rm "$loader"
wpc eval 'do_action( "admin_init" );'
[ -f "$loader" ] || fail "loader niet hersteld"
ok "loader hersteld via admin_init"

admin="$(wpc user list --role=administrator --field=ID | head -n 1)"
rm "$loader"
trap 'chmod u+w "$site/wp-content/mu-plugins"' EXIT
chmod a-w "$site/wp-content/mu-plugins"
notice="$(wpc --user="$admin" eval 'do_action( "admin_init" ); do_action( "admin_notices" );' || true)"
chmod u+w "$site/wp-content/mu-plugins"
[ ! -f "$loader" ] || fail "loader geplaatst in alleen-lezen map"
echo "$notice" | grep -q "mu-loader kon niet worden geplaatst" || fail "geen melding bij mislukte loader"
ok "melding als loader niet geplaatst kan worden"
wpc eval 'do_action( "admin_init" );'
[ -f "$loader" ] || fail "loader niet hersteld na rechtenherstel"

wpc plugin deactivate studiosambal-plugin >/dev/null
[ "$(wpc eval 'echo function_exists( "studiosambal_env_type" ) ? "ja" : "nee";')" = "ja" ] || fail "draait niet na deactiveren"
ok "draait door na deactiveren (via loader)"
wpc plugin activate studiosambal-plugin >/dev/null
ok "activeren naast loader (geen dubbele definities)"

# Updatecheck: met een lage versie moet de laatste tag als update verschijnen.
latest="$(git -C "$repo" ls-remote --tags --refs origin 'v*' | sed 's#.*refs/tags/v##' | sort -t. -k1,1n -k2,2n -k3,3n | tail -n 1)"
sed -i '' -E 's/^\* Version: .*/* Version: 0.0.1/' "$plugin/studiosambal-plugin.php"
wpc option delete external_updates-studiosambal-plugin >/dev/null || true
wpc eval 'do_action( "puc_cron_check_updates-studiosambal-plugin" );'
found="$(wpc plugin list --name=studiosambal-plugin --field=update_version)"
wpc plugin install "$zip" --force --activate >/dev/null
wpc option delete external_updates-studiosambal-plugin >/dev/null || true
[ "$found" = "$latest" ] || fail "updatecheck zag '$found', verwacht '$latest'"
ok "updatecheck ziet v$latest op GitHub"

echo "Integratie OK op $site"
