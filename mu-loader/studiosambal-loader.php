<?php
/**
* Plugin Name: Studio Sambal (loader)
* Description: Laadt de plugin Studio Sambal altijd, ook als hij gedeactiveerd is. Updates lopen via de plugin zelf; dit bestand verandert nooit.
* Author: Studio Sambal
**/

defined( 'ABSPATH' ) || exit;

if ( file_exists( WP_PLUGIN_DIR . '/studiosambal-plugin/studiosambal-plugin.php' ) ) {
   require_once WP_PLUGIN_DIR . '/studiosambal-plugin/studiosambal-plugin.php';
}
