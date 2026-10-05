# Studio Sambal

WordPress-plugin met omgevingsgedrag op één plek:

- **Niet-productie** (lokaal, staging, FlyWP-testdomeinen): zoekmachines ontmoedigd via robots.txt, meta robots, Rank Math en `X-Robots-Tag`. Dit is geen afscherming: statische bestanden en serverside caches die PHP overslaan vallen erbuiten. Echt afschermen doe je met toegangsbeveiliging op de server.
- **Productie:** waarschuwing als "Zoekmachines ontmoedigen" aanstaat.
- **Overal:** omgevingslabel in de adminbalk.
- **Lokaal:** ontbrekende uploads komen van de externe omgeving. Gewone bestanden via een URL-rewrite of redirect, SVG's (die Bricks van schijf leest) worden één keer gedownload.

## Installeren op een site

Upload `studiosambal-plugin.zip` (van de laatste release, zie hieronder) via Plugins → Nieuwe plugin, of in bulk via WP Umbrella, en activeer hem. Bij activeren zet de plugin zelf `mu-plugins/studiosambal-loader.php` neer. Die loader zorgt dat de plugin altijd draait, ook als iemand hem deactiveert, en verandert nooit.

Een oude mu-plugin `mu-plugins/studiosambal-plugin.php` verwijder je bij de overstap.

De map moet `studiosambal-plugin` heten; de standaard-zips van GitHub hebben een andere mapnaam, gebruik daarom `bin/zip.sh`.

## Instellingen (wp-config.php, optioneel)

```php
define( 'WP_ENVIRONMENT_TYPE', 'staging' );  // local | development | staging | production
define( 'STUDIOSAMBAL_REMOTE_UPLOADS_URL', 'https://klant.nl/wp-content/uploads' );  // alleen lokaal
```

Extra domeinen blokkeren: filter `studiosambal_blocked_domains`.

## Updaten

Sites zien nieuwe versies zoals elke andere plugin: in wp-admin onder Updates, en via
`wp plugin update studiosambal-plugin` (dus ook via `/wp-update`). De check loopt tegen de tags van deze repo.

## Nieuwe versie uitbrengen

```sh
bin/test.sh               # unittests (gebruikt WordPress-core van ~/Sites/stichtingkego, of WP_CORE_DIR)
bin/integration.sh        # installeren, loader, deactiveren, updatecheck op een lokale site (WP_SITE)
bin/release.sh 3.3.1      # versie zetten, testen, commit, tag, push
bin/zip.sh                # studiosambal-plugin.zip van de laatste tag, voor nieuwe installaties
```
