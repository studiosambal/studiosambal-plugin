<?php
/**
* Plugin Name: Studio Sambal
* Plugin URI: https://github.com/studiosambal/studiosambal-plugin
* Description: Omgevingsgedrag op één plek. Niet-productie: zoekmachine-indexering geblokkeerd. Productie: waarschuwing als zoekmachines ontmoedigd worden. Overal: omgevingslabel in de adminbalk. Lokaal: ontbrekende uploads (ook SVG's die Bricks van schijf leest) worden van de externe omgeving geladen. Wordt via een mu-loader altijd geladen, ook als hij gedeactiveerd is.
* Version: 3.3.1
* Requires PHP: 7.4
* Author: Studio Sambal
* Author URI: https://studiosambal.nl
* Update URI: https://github.com/studiosambal/studiosambal-plugin
*
* Vervangt de mu-plugin 'studiosambal-plugin.php' (3.x), 'Studio Sambal – Google Blocker' (2.x)
* en 'Local Uploads from Production' (1.0).
*
* Instellingen (optioneel, in wp-config.php):
*   define( 'WP_ENVIRONMENT_TYPE', 'staging' );  // local | development | staging | production
*   define( 'STUDIOSAMBAL_REMOTE_UPLOADS_URL', 'https://klant.flywp.xyz/wp-content/uploads' );  // alleen lokaal
**/

defined( 'ABSPATH' ) || exit;

// De mu-loader en WordPress kunnen dit bestand allebei laden; één keer is genoeg.
if ( defined( 'STUDIOSAMBAL_PLUGIN_VERSION' ) ) {
   return;
}
define( 'STUDIOSAMBAL_PLUGIN_VERSION', '3.3.1' );

require __DIR__ . '/src/omgeving.php';

/**
* Zet de mu-loader neer als hij ontbreekt, zodat de plugin ook gedeactiveerd
* blijft draaien. Zo volstaat een gewone installatie (wp-admin, WP Umbrella).
*/
function studiosambal_install_mu_loader()
{
   $target = WPMU_PLUGIN_DIR . '/studiosambal-loader.php';
   if ( is_file( $target ) ) {
      return true;
   }
   // @: anders een PHP-warning bij elke beheerpagina; de melding hieronder meldt het al.
   return wp_mkdir_p( WPMU_PLUGIN_DIR )
      && @copy( __DIR__ . '/mu-loader/studiosambal-loader.php', $target )
      && is_file( $target );
}
register_activation_hook( __FILE__, 'studiosambal_install_mu_loader' );
add_action( 'admin_init', 'studiosambal_install_mu_loader' );

/**
* Lukt het neerzetten niet (rechten), dan draait de plugin alleen zolang hij
* actief is. Dat moet zichtbaar zijn in plaats van stil te mislukken.
*/
function studiosambal_mu_loader_notice()
{
   if ( ! current_user_can( 'activate_plugins' ) || is_file( WPMU_PLUGIN_DIR . '/studiosambal-loader.php' ) ) {
      return;
   }
   printf(
      '<div class="notice notice-error"><p><strong>Studio Sambal:</strong> %s <code>%s</code> → <code>%s</code></p></div>',
      esc_html( 'De mu-loader kon niet worden geplaatst, dus de plugin stopt als hij gedeactiveerd wordt. Kopieer hem handmatig:' ),
      esc_html( 'wp-content/plugins/studiosambal-plugin/mu-loader/studiosambal-loader.php' ),
      esc_html( 'wp-content/mu-plugins/' )
   );
}
add_action( 'admin_notices', 'studiosambal_mu_loader_notice' );

// Updates via GitHub-tags, zichtbaar in wp-admin en via `wp plugin update studiosambal-plugin`.
require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
   'https://github.com/studiosambal/studiosambal-plugin/',
   __FILE__,
   'studiosambal-plugin'
);
