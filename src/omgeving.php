<?php
/**
* Omgevingsgedrag: indexeringsblokkade buiten productie, productiewaarschuwing,
* omgevingslabel in de adminbalk en lokaal ontbrekende uploads van extern.
* Geladen door studiosambal-plugin.php; zie README.md voor instellingen.
*/

defined( 'ABSPATH' ) || exit;

/**
* Domeinen waarop (inclusief subdomeinen) altijd geblokkeerd wordt.
* Uit te breiden via de filter 'studiosambal_blocked_domains'.
*
* @return string[]
*/
function studiosambal_env_blocked_domains()
{
   return (array) apply_filters( 'studiosambal_blocked_domains', array(
      'studiosambal.nl',
      'sambal.studio',
      'flywp.xyz',
   ) );
}

/**
* Hostname van de huidige request, of van home_url() als er geen request is (WP-CLI, cron).
*
* @return string
*/
function studiosambal_env_host()
{
   $host = $_SERVER['HTTP_HOST'] ?? (string) wp_parse_url( get_option( 'home' ), PHP_URL_HOST );
   return studiosambal_env_normalize_host( $host );
}

/** Normaliseer DNS-namen, poorten en IPv6-loopback. */
function studiosambal_env_normalize_host( $host )
{
   $host = strtolower( trim( (string) $host ) );
   if ( '::1' === $host || preg_match( '/^\[::1\](?::\d+)?$/', $host ) ) {
      return '::1';
   }
   return rtrim( preg_replace( '/:\d+$/', '', $host ), '.' );
}

/**
* Leidt de omgeving af uit een hostname.
*
* - .test, .localhost, localhost of loopback-IP        → local
* - domein uit studiosambal_env_blocked_domains()       → staging
*   (of een subdomein daarvan; suffix op een labelgrens, dus niet op nietstudiosambal.nl)
* - begint met dev/development/stage/staging of heeft test als label → staging
*   (bewust géén substring-match, anders zou bijv. protest.nl geraakt worden)
* - anders                                              → production
*
* @param string $host
* @return string
*/
function studiosambal_env_type_for_host( $host )
{
   $host = studiosambal_env_normalize_host( $host );
   if ( '' === $host ) {
      return 'production';
   }

   $host_parts = explode( '.', $host );
   if ( in_array( end( $host_parts ), array( 'test', 'localhost' ), true ) || '::1' === $host
      || ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && '127' === $host_parts[0] ) ) {
      return 'local';
   }

   foreach ( studiosambal_env_blocked_domains() as $block_domain ) {
      if ( ! is_string( $block_domain ) || '' === trim( $block_domain, '. ' ) ) {
         continue;
      }
      $block_domain = strtolower( trim( $block_domain, '. ' ) );
      if ( $host === $block_domain || substr( $host, -strlen( '.' . $block_domain ) ) === '.' . $block_domain ) {
         return 'staging';
      }
   }

   if ( in_array( $host_parts[0], array( 'dev', 'development', 'stage', 'staging' ), true ) || in_array( 'test', $host_parts, true ) ) {
      return 'staging';
   }

   return 'production';
}

/**
* Expliciet niet-productie wint. Bij production (ook de WordPress-default)
* blijven bekende lokale en staging-hosts beschermd. Controleer ook de home-URL,
* zodat een andere Host-header de blokkade niet opheft. Wijzigt WordPress niet.
*
* @return string local | development | staging | production
*/
function studiosambal_env_type()
{
   $environment = wp_get_environment_type();
   if ( 'production' !== $environment ) {
      return $environment;
   }
   $home_environment = studiosambal_env_type_for_host( wp_parse_url( get_option( 'home' ), PHP_URL_HOST ) );
   if ( 'production' !== $home_environment ) {
      return $home_environment;
   }
   return studiosambal_env_type_for_host( studiosambal_env_host() );
}

/* ------------------------------------------------------------------------- *
 * Niet-productie: zoekmachines blokkeren
 * ------------------------------------------------------------------------- */

/**
* Zoekmachines mogen pagina's crawlen, zodat ze de noindex (meta en
* X-Robots-Tag) lezen; met 'Disallow: /' zien ze die niet en kan een gelinkte
* URL alsnog in de resultaten komen. Uploads krijgen geen noindex-header (de
* server levert ze zonder PHP), dus die zijn voor zoekmachines verboden terrein.
* Alle andere bots (AI-crawlers, SEO-tools) mogen niets.
* WordPress zet zelf 'Disallow: /' zodra blog_public 0 is, daarom wordt de
* hele robots.txt vervangen.
*
* @return string
*/
function studiosambal_env_robots_txt()
{
   $uploads = wp_parse_url( wp_get_upload_dir()['baseurl'] ?? '', PHP_URL_PATH );
   $uploads = '/' . trim( is_string( $uploads ) && '' !== trim( $uploads, '/' ) ? $uploads : 'wp-content/uploads', '/' ) . '/';
   $search_engines = (array) apply_filters( 'studiosambal_robots_search_engines', array(
      'Googlebot',
      'Bingbot',
      'Applebot',
      'DuckDuckBot',
   ) );

   $robots = '';
   foreach ( $search_engines as $agent ) {
      $robots .= 'User-agent: ' . $agent . "\n";
   }
   return $robots . 'Disallow: ' . $uploads . "\n\nUser-agent: *\nDisallow: /\n";
}

/**
* Robots-meta via de WordPress-API, zodat er één tag in de head staat
* in plaats van een tweede die botst met die van WordPress of een SEO-plugin.
*
* @param array $robots
* @return array
*/
function studiosambal_env_wp_robots( $robots )
{
   unset( $robots['index'], $robots['follow'], $robots['max-image-preview'] );
   $robots['noindex']   = true;
   $robots['nofollow']  = true;
   $robots['nosnippet'] = true;
   $robots['noarchive'] = true;
   return $robots;
}

/**
* Rank Math schrijft zijn eigen robots-tag; forceer daar ook noindex.
*
* @param array $robots
* @return array
*/
function studiosambal_env_rank_math_robots( $robots )
{
   unset( $robots['index'], $robots['follow'] );
   return array_merge( (array) $robots, array(
      'noindex'   => 'noindex',
      'nofollow'  => 'nofollow',
      'nosnippet' => 'nosnippet',
      'noarchive' => 'noarchive',
   ) );
}

/**
* Header voor responses die WordPress doorlopen, inclusief REST en feeds.
* Statische uploads en server/page-cache die PHP overslaan vereisen serverheaders.
*/
function studiosambal_env_robots_header()
{
   if ( ! headers_sent() ) {
      header( 'X-Robots-Tag: noindex, nofollow, nosnippet, noarchive', true );
   }
}

function studiosambal_env_admin_notice()
{
   global $pagenow;
   if ( 'index.php' === $pagenow ) {
      printf(
         '<div class="notice notice-warning is-dismissible"><p><b>Studio Sambal blocker actief (%s):</b> Zoekmachines worden momenteel geblokkeerd</p></div>',
         esc_html( studiosambal_env_type() )
      );
   }
}

/* ------------------------------------------------------------------------- *
 * Productie: waarschuwen als de site voor zoekmachines verborgen is
 * ------------------------------------------------------------------------- */

/**
* Opgeslagen 'Zoekmachines ontmoedigen'. Alleen zinvol op productie: daar
* filtert deze plugin blog_public niet, dus dit is de echte databasewaarde
* (bijvoorbeeld meegekomen met een databasemigratie vanaf staging).
*
* @return bool
*/
function studiosambal_env_production_hidden()
{
   return 'production' === studiosambal_env_type() && '0' === (string) get_option( 'blog_public' );
}

function studiosambal_env_production_notice()
{
   if ( ! current_user_can( 'manage_options' ) || ! studiosambal_env_production_hidden() ) {
      return;
   }
   printf(
      '<div class="notice notice-error"><p><b>Let op: deze productiesite is verborgen voor zoekmachines.</b> Zet bij <a href="%s">Instellingen → Lezen</a> het vinkje "Zoekmachines ontmoedigen deze site te indexeren" uit.</p></div>',
      esc_url( admin_url( 'options-reading.php' ) )
   );
}

/* ------------------------------------------------------------------------- *
 * Overal: omgevingslabel in de adminbalk
 * ------------------------------------------------------------------------- */

/**
* @param WP_Admin_Bar $admin_bar
*/
function studiosambal_env_admin_bar( $admin_bar )
{
   $environment = studiosambal_env_type();
   // Productie krijgt geen label, behalve als waarschuwing bij 'Zoekmachines ontmoedigen'.
   if ( 'production' === $environment && ! studiosambal_env_production_hidden() ) {
      return;
   }
   $labels = array(
      'local'       => 'Lokaal',
      'development' => 'Development',
      'staging'     => 'Staging',
      'production'  => 'Productie',
   );
   $label = $labels[ $environment ] ?? ucfirst( $environment );
   $node = array(
      'id'    => 'studiosambal-env',
      'title' => esc_html( $label ),
      'meta'  => array(
         'class' => 'studiosambal-env studiosambal-env--' . sanitize_html_class( $environment ),
         'title' => 'Zoekmachines ontmoedigd door Studio Sambal',
      ),
   );
   if ( studiosambal_env_production_hidden() ) {
      $node['title'] = esc_html( $label . ' · niet indexeerbaar' );
      $node['href'] = admin_url( 'options-reading.php' );
      $node['meta']['class'] .= ' studiosambal-env--hidden';
      $node['meta']['title'] = 'Zoekmachines worden ontmoedigd (Instellingen → Lezen)';
   }
   $admin_bar->add_node( $node );
}

function studiosambal_env_admin_bar_style()
{
   if ( ! is_admin_bar_showing() ) {
      return;
   }
   // !important: de adminbalk zet hover- en focuskleuren met hogere specificiteit.
   echo '<style id="studiosambal-env">'
      . '#wpadminbar .studiosambal-env > .ab-item{color:#fff!important;font-weight:600;background:#c62828!important}'
      . '#wpadminbar .studiosambal-env--local > .ab-item{background:#2e7d32!important}'
      . '#wpadminbar .studiosambal-env--development > .ab-item,#wpadminbar .studiosambal-env--staging > .ab-item{background:#e65100!important}'
      . '#wpadminbar .studiosambal-env--hidden > .ab-item{background:#c62828!important;color:#fff!important;font-weight:600}'
      . '@media screen and (max-width:782px){#wpadminbar li.studiosambal-env{display:block}#wpadminbar .studiosambal-env > .ab-item{font-size:13px;padding:0 8px}}'
      . '</style>';
}

/* ------------------------------------------------------------------------- *
 * Lokaal: ontbrekende uploads van de externe omgeving laden
 * ------------------------------------------------------------------------- */

/**
* Zet een lokale upload-URL om naar de externe URL, maar alleen als het
* bestand lokaal ontbreekt. Bestanden die lokaal bestaan blijven lokaal.
*
* @param string $url
* @return string
*/
function studiosambal_env_remote_upload_url( $url )
{
   if ( ! is_string( $url ) || '' === $url || ! studiosambal_env_remote_uploads_base() ) {
      return $url;
   }

   // WordPress cachet dit zelf per site; geen static die switch_to_blog() breekt.
   $upload_dir = wp_get_upload_dir();
   $base = wp_parse_url( $upload_dir['baseurl'] );
   $source = wp_parse_url( $url );
   if ( empty( $upload_dir['basedir'] ) || empty( $base['host'] ) || empty( $source['host'] )
      || ! in_array( strtolower( $source['scheme'] ?? 'https' ), array( 'http', 'https' ), true )
      || isset( $source['user'] ) || isset( $source['pass'] )
      || strtolower( $base['host'] ) !== strtolower( $source['host'] )
      || ( $base['port'] ?? null ) !== ( $source['port'] ?? null ) ) {
      return $url;
   }

   $prefix = rtrim( $base['path'] ?? '', '/' ) . '/';
   $path = $source['path'] ?? '';
   if ( 0 !== strpos( $path, $prefix ) ) {
      return $url;
   }

   $relative = substr( $path, strlen( $prefix ) );
   $decoded = rawurldecode( $relative );
   $segments = explode( '/', $decoded );
   // Geen mappen, traversal of besturingskarakters doorgeven aan filesystem/URL.
   if ( array_intersect( array( '', '.', '..' ), $segments ) || false !== strpos( $decoded, '\\' )
      || preg_match( '/[\x00-\x1f\x7f]/', $decoded ) ) {
      return $url;
   }
   if ( file_exists( rtrim( $upload_dir['basedir'], '/\\' ) . '/' . $decoded ) ) {
      return $url;
   }

   return studiosambal_env_remote_uploads_base() . '/' . $relative
      . ( isset( $source['query'] ) ? '?' . $source['query'] : '' )
      . ( isset( $source['fragment'] ) ? '#' . $source['fragment'] : '' );
}

/** Alleen een absolute HTTP(S)-uploads-URL zonder credentials/query/fragment. */
function studiosambal_env_remote_uploads_base()
{
   if ( ! defined( 'STUDIOSAMBAL_REMOTE_UPLOADS_URL' ) || ! is_string( STUDIOSAMBAL_REMOTE_UPLOADS_URL ) ) {
      return '';
   }
   $url = trim( STUDIOSAMBAL_REMOTE_UPLOADS_URL );
   $parts = wp_parse_url( $url );
   if ( empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true )
      || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] )
      || preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) {
      return '';
   }
   return untrailingslashit( $url );
}

/**
* @param array|false $image
* @return array|false
*/
function studiosambal_env_remote_image_src( $image )
{
   if ( ! empty( $image[0] ) ) {
      $image[0] = studiosambal_env_remote_upload_url( studiosambal_env_local_upload_url( $image[0] ) );
   }
   return $image;
}

/**
* Zet een externe upload-URL terug naar de lokale. WordPress bouwt een
* formaat (thumbnail) op de URL van het origineel; ontbreekt alleen het
* origineel lokaal, dan is die URL al extern terwijl het formaat lokaal
* kan bestaan. Daarna beslist studiosambal_env_remote_upload_url() opnieuw.
*
* @param string $url
* @return string
*/
function studiosambal_env_local_upload_url( $url )
{
   $remote = studiosambal_env_remote_uploads_base();
   if ( ! is_string( $url ) || '' === $remote || 0 !== strpos( $url, $remote . '/' ) ) {
      return $url;
   }
   return untrailingslashit( wp_get_upload_dir()['baseurl'] ?? '' ) . substr( $url, strlen( $remote ) );
}

/**
* @param array $sources
* @return array
*/
function studiosambal_env_remote_srcset( $sources )
{
   foreach ( (array) $sources as $key => $source ) {
      if ( ! empty( $source['url'] ) ) {
         $sources[ $key ]['url'] = studiosambal_env_remote_upload_url( studiosambal_env_local_upload_url( $source['url'] ) );
      }
   }
   return $sources;
}

/**
* Externe URL voor een directe request naar een lokaal ontbrekende upload,
* of '' als er niets om te leiden is. Vangt ook URL's die niet via de
* attachment-filters lopen (hardcoded in Bricks-content, CSS-achtergronden).
* De request-URI wordt op de uploadbasis gezet, zodat alle controles van
* studiosambal_env_remote_upload_url() gelden.
*
* @return string
*/
function studiosambal_env_missing_upload_redirect_target()
{
   $method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
   $uri = $_SERVER['REQUEST_URI'] ?? '';
   if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || ! is_string( $uri ) || '/' !== substr( $uri, 0, 1 ) ) {
      return '';
   }
   $base = wp_parse_url( wp_get_upload_dir()['baseurl'] ?? '' );
   if ( empty( $base['host'] ) ) {
      return '';
   }
   $local = ( $base['scheme'] ?? 'https' ) . '://' . $base['host'] . ( isset( $base['port'] ) ? ':' . $base['port'] : '' ) . $uri;
   $target = studiosambal_env_remote_upload_url( $local );
   return $target === $local ? '' : $target;
}

/** Grotere SVG's worden niet gedownload; een logo of icoon is een paar KB. */
define( 'STUDIOSAMBAL_SVG_MAX_BYTES', 1024 * 1024 );

/**
* Haalt een lokaal ontbrekende SVG-bijlage één keer van de externe omgeving
* en slaat hem lokaal op. Bricks (SVG-element) leest SVG's van schijf om ze
* inline te zetten; een URL-redirect helpt daar niet.
*
* @param string|false $file
* @param int $attachment_id
* @return string|false
*/
function studiosambal_env_fetch_missing_svg( $file, $attachment_id )
{
   static $tried = array();

   if ( ! is_string( $file ) || '' === $file || file_exists( $file ) || isset( $tried[ $attachment_id ] )
      || 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
      return $file;
   }

   $relative = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
   $basedir = rtrim( wp_get_upload_dir()['basedir'] ?? '', '/\\' );
   $segments = explode( '/', $relative );
   // Alleen het bestand dat bij deze bijlage hoort, binnen de uploadmap.
   if ( '' === $basedir || $file !== $basedir . '/' . $relative
      || array_intersect( array( '', '.', '..' ), $segments ) || false !== strpos( $relative, '\\' )
      || preg_match( '/[\x00-\x1f\x7f]/', $relative ) ) {
      return $file;
   }
   $tried[ $attachment_id ] = true;

   // Mislukte pogingen even onthouden, zodat niet elke request productie raakt.
   $failed_key = 'studiosambal_svg_fail_' . $attachment_id;
   if ( get_transient( $failed_key ) ) {
      return $file;
   }

   $url = studiosambal_env_remote_uploads_base() . '/' . implode( '/', array_map( 'rawurlencode', $segments ) );
   $response = wp_remote_get( $url, array(
      'timeout'             => 5,
      'redirection'         => 2,
      'limit_response_size' => STUDIOSAMBAL_SVG_MAX_BYTES + 1,
   ) );
   $body = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );

   // Via een tijdelijk bestand, zodat een afgebroken schrijfactie geen half bestand achterlaat.
   $temp = $file . '.' . bin2hex( random_bytes( 4 ) ) . '.tmp';
   if ( 200 !== wp_remote_retrieve_response_code( $response ) || strlen( $body ) > STUDIOSAMBAL_SVG_MAX_BYTES
      || ! studiosambal_env_is_svg_document( $body ) || ! wp_mkdir_p( dirname( $file ) )
      || strlen( $body ) !== file_put_contents( $temp, $body ) || ! rename( $temp, $file ) ) {
      if ( file_exists( $temp ) ) {
         unlink( $temp );
      }
      set_transient( $failed_key, 1, 10 * MINUTE_IN_SECONDS );
   }

   return $file;
}

/**
* Is dit een SVG-document, en niet bijvoorbeeld een HTML-pagina met een
* SVG-icoon erin? Het root-element moet <svg> zijn; ervoor mogen alleen een
* BOM, XML-declaratie, doctype, commentaar en witruimte staan.
*
* @param string $body
* @return bool
*/
function studiosambal_env_is_svg_document( $body )
{
   if ( ! is_string( $body ) ) {
      return false;
   }
   $prolog = '/\A(?:\xEF\xBB\xBF)?(?:\s+|<\?xml\b[^>]*\?>|<!DOCTYPE\s+svg\b[^>]*>|<!--.*?-->)*/is';
   $rest = preg_replace( $prolog, '', $body, 1 );
   return is_string( $rest ) && 1 === preg_match( '/\A<svg[\s>\/]/i', $rest )
      && 1 === preg_match( '/<\/svg>\s*\z/i', $rest );
}

function studiosambal_env_redirect_missing_upload()
{
   $target = studiosambal_env_missing_upload_redirect_target();
   if ( '' !== $target && wp_redirect( $target, 302, 'Studio Sambal' ) ) {
      exit;
   }
}

/* ------------------------------------------------------------------------- *
 * Activeren
 * ------------------------------------------------------------------------- */

function studiosambal_env_bootstrap()
{
   $environment = studiosambal_env_type();
   if ( 'production' !== $environment ) {
      // Alleen runtime; de opgeslagen productie-instelling blijft intact.
      add_filter( 'pre_option_blog_public', '__return_zero', PHP_INT_MAX );
      add_filter( 'robots_txt', 'studiosambal_env_robots_txt', PHP_INT_MAX );
      add_filter( 'wp_robots', 'studiosambal_env_wp_robots', PHP_INT_MAX );
      add_filter( 'rank_math/frontend/robots', 'studiosambal_env_rank_math_robots', PHP_INT_MAX );
      studiosambal_env_robots_header();
      add_action( 'send_headers', 'studiosambal_env_robots_header', PHP_INT_MAX );
      add_action( 'admin_notices', 'studiosambal_env_admin_notice' );
   } else {
      add_action( 'admin_notices', 'studiosambal_env_production_notice' );
   }

   // Vóór alle core-items (sidebar-toggle op 0, WordPress-logo op 10), dus helemaal links.
   add_action( 'admin_bar_menu', 'studiosambal_env_admin_bar', -1 );
   add_action( 'wp_head', 'studiosambal_env_admin_bar_style' );
   add_action( 'admin_head', 'studiosambal_env_admin_bar_style' );

   if ( 'local' === $environment && studiosambal_env_remote_uploads_base() ) {
      add_filter( 'wp_get_attachment_url', 'studiosambal_env_remote_upload_url' );
      add_filter( 'wp_get_attachment_image_src', 'studiosambal_env_remote_image_src' );
      add_filter( 'wp_calculate_image_srcset', 'studiosambal_env_remote_srcset' );
      add_filter( 'get_attached_file', 'studiosambal_env_fetch_missing_svg', 10, 2 );
      studiosambal_env_redirect_missing_upload();
   }
}

// Wacht tot plugins en thema hun domeinfilter hebben kunnen registreren.
add_action( 'init', 'studiosambal_env_bootstrap', 0 );
