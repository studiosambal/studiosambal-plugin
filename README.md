# Studio Sambal

WordPress-plugin met omgevingsgedrag op één plek:

- **Niet-productie** (lokaal, staging, FlyWP-testdomeinen): zoekmachine-indexering geblokkeerd (robots.txt, meta robots, Rank Math, `X-Robots-Tag`).
- **Productie:** waarschuwing als "Zoekmachines ontmoedigen" aanstaat.
- **Overal:** omgevingslabel in de adminbalk.
- **Lokaal:** ontbrekende uploads komen van de externe omgeving. Gewone bestanden via een URL-rewrite of redirect, SVG's (die Bricks van schijf leest) worden één keer gedownload.

## Installeren op een site

```sh
cd wp-content/plugins
git clone --depth 1 https://github.com/studiosambal/studiosambal-plugin.git
rm -rf studiosambal-plugin/.git
cp studiosambal-plugin/mu-loader/studiosambal-loader.php ../mu-plugins/
wp plugin activate studiosambal-plugin
```

De map moet `studiosambal-plugin` heten. De zips van GitHub hebben een andere mapnaam; hernoem die eerst als je via een zip installeert.

De **mu-loader** zorgt dat de plugin altijd draait, ook als iemand hem deactiveert. Hij verandert nooit, dus je hoeft hem maar één keer te plaatsen. Activeren is niet nodig, maar wel netjes; dubbel laden wordt afgevangen.

Een oude mu-plugin `mu-plugins/studiosambal-plugin.php` verwijder je bij de overstap.

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
bin/test.sh               # tests (gebruikt WordPress-core van ~/Sites/stichtingkego, of WP_CORE_DIR)
bin/release.sh 3.3.1      # versie zetten, testen, commit, tag, push
```
