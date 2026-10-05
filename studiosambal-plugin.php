<?php
/**
* Plugin Name: Studio Sambal
* Plugin URI: https://github.com/studiosambal/studiosambal-plugin
* Description: Omgevingsgedrag op één plek. Niet-productie: zoekmachine-indexering geblokkeerd. Productie: waarschuwing als zoekmachines ontmoedigd worden. Overal: omgevingslabel in de adminbalk. Lokaal: ontbrekende uploads (ook SVG's die Bricks van schijf leest) worden van de externe omgeving geladen. Wordt via een mu-loader altijd geladen, ook als hij gedeactiveerd is.
* Version: 3.3.0
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
define( 'STUDIOSAMBAL_PLUGIN_VERSION', '3.3.0' );

require __DIR__ . '/src/omgeving.php';

/**
* Zet de mu-loader neer als hij ontbreekt, zodat de plugin ook gedeactiveerd
* blijft draaien. Zo volstaat een gewone installatie (wp-admin, WP Umbrella).
*/
function studiosambal_install_mu_loader()
{
   $target = WPMU_PLUGIN_DIR . '/studiosambal-loader.php';
   if ( file_exists( $target ) || ! wp_mkdir_p( WPMU_PLUGIN_DIR ) ) {
      return;
   }
   @copy( __DIR__ . '/mu-loader/studiosambal-loader.php', $target );
}
register_activation_hook( __FILE__, 'studiosambal_install_mu_loader' );
add_action( 'admin_init', 'studiosambal_install_mu_loader' );

// Updates via GitHub-tags, zichtbaar in wp-admin en via `wp plugin update studiosambal-plugin`.
require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
   'https://github.com/studiosambal/studiosambal-plugin/',
   __FILE__,
   'studiosambal-plugin'
);
