<?php
/**
 * Run: WP_CORE_DIR=~/Sites/<site> php tests/omgeving-test.php [valid|unset|invalid|credentials|query|fragment|whitespace]
 * Of alles tegelijk: bin/test.sh
 */
if ( PHP_SAPI !== 'cli' ) {
   exit;
}
// WordPress-core van een willekeurige lokale site; alleen wp-includes wordt gebruikt.
define( 'ABSPATH', rtrim( getenv( 'WP_CORE_DIR' ) ?: getenv( 'HOME' ) . '/Sites/stichtingkego', '/' ) . '/' );
define( 'WP_RUN_CORE_TESTS', true );
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/load.php';
require ABSPATH . 'wp-includes/http.php';

// Alleen database en upload-directory zijn fixtures; hooks, URL-parser en
// omgevingsinstellingen gebruiken de echte WordPress-implementatie.
$home = 'https://example.org';
$blog_public = '1';
function get_option( $name ) {
   return array( 'home' => $GLOBALS['home'], 'blog_public' => $GLOBALS['blog_public'] )[ $name ] ?? null;
}
// Minimale weergave-stubs voor de adminbalk en meldingen.
function esc_html( $value ) {
   return htmlspecialchars( (string) $value, ENT_QUOTES );
}
function esc_url( $value ) {
   return (string) $value;
}
function admin_url( $path = '' ) {
   return 'https://example.org/wp-admin/' . $path;
}
function sanitize_html_class( $value ) {
   return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
}
function current_user_can( $capability ) {
   return true;
}
class Fake_Admin_Bar {
   public $nodes = array();
   public function add_node( $node ) {
      $this->nodes[ $node['id'] ] = $node;
   }
}
function wp_get_upload_dir() {
   return $GLOBALS['upload_dir'];
}
function untrailingslashit( $value ) {
   return rtrim( $value, '/\\' );
}
function __return_zero() {
   return 0;
}
// Fixtures voor SVG-bijlagen: mime-type, metadata, transients en HTTP.
define( 'MINUTE_IN_SECONDS', 60 );
$attachments = array();
$upload_dir = array( 'baseurl' => 'https://example.org/wp-content/uploads', 'basedir' => sys_get_temp_dir() );
$transients = array();
$http_requests = array();
$http_response = null;
function get_post_mime_type( $id ) {
   return $GLOBALS['attachments'][ $id ]['mime'] ?? false;
}
function get_post_meta( $id, $key, $single ) {
   return '_wp_attached_file' === $key ? ( $GLOBALS['attachments'][ $id ]['file'] ?? '' ) : '';
}
function get_transient( $key ) {
   return $GLOBALS['transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $expiration ) {
   $GLOBALS['transients'][ $key ] = $value;
   return true;
}
function wp_mkdir_p( $dir ) {
   return is_dir( $dir ) || mkdir( $dir, 0777, true );
}
class WP_Http {
   public function get( $url, $args ) {
      $GLOBALS['http_requests'][] = $url;
      return $GLOBALS['http_response'];
   }
}

$mode = $argv[1] ?? 'valid';
$remote_options = array(
   'valid'       => 'https://remote.example.org/wp-content/uploads/',
   'invalid'     => 'javascript:alert(1)',
   'credentials' => 'https://user:secret@remote.example.org/uploads',
   'query'       => 'https://remote.example.org/uploads?token=value',
   'fragment'    => 'https://remote.example.org/uploads#fragment',
   'whitespace'  => "https://remote.example.org/up\nloads",
);
if ( isset( $remote_options[ $mode ] ) ) {
   define( 'STUDIOSAMBAL_REMOTE_UPLOADS_URL', $remote_options[ $mode ] );
} elseif ( 'unset' !== $mode ) {
   throw new RuntimeException( 'Unknown mode' );
}
require dirname( __DIR__ ) . '/src/omgeving.php';

$checks = 0;
function check( $expected, $actual, $label ) {
   ++$GLOBALS['checks'];
   if ( $expected !== $actual ) {
      throw new RuntimeException( $label . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
   }
}
function reset_environment( $environment, $host, $home_url ) {
   // Elke scenario start met een schone WordPress-hookregistratie.
   $GLOBALS['wp_filter'] = array();
   $GLOBALS['home'] = $home_url;
   putenv( 'WP_ENVIRONMENT_TYPE=' . $environment );
   unset( $_SERVER['HTTP_HOST'] );
   if ( null !== $host ) {
      $_SERVER['HTTP_HOST'] = $host;
   }
   add_action( 'init', 'studiosambal_env_bootstrap', 0 );
}

foreach ( array(
   'EXAMPLE.TEST.:8080' => 'local', 'localhost' => 'local',
   'shop.localhost' => 'local', '127.0.0.1:8080' => 'local',
   '[::1]:8080' => 'local', '::1' => 'local',
   'demo.flywp.xyz' => 'staging', 'studiosambal.nl' => 'staging',
   'demo.sambal.studio' => 'staging', 'staging.example.org' => 'staging',
   'stage.example.org' => 'staging', 'dev.example.org' => 'staging',
   'development.example.org' => 'staging', 'a.test.example.org' => 'staging',
   'protest.nl' => 'production', 'nietstudiosambal.nl' => 'production',
   'flywp.xyz.example.org' => 'production', 'example.org' => 'production',
) as $host => $expected ) {
   check( $expected, studiosambal_env_type_for_host( $host ), $host );
}

foreach ( array(
   array( 'production', 'example.org', 'https://example.org', 'production' ),
   array( 'production', 'demo.flywp.xyz', 'https://demo.flywp.xyz', 'staging' ),
   array( 'production', 'example.org', 'https://demo.flywp.xyz', 'staging' ),
   array( 'production', 'staging.example.org', 'https://example.org', 'staging' ),
   array( 'production', 'example.test', 'https://example.test', 'local' ),
   array( 'staging', 'example.org', 'https://example.org', 'staging' ),
   array( 'local', 'example.org', 'https://example.org', 'local' ),
   array( 'development', 'example.org', 'https://example.org', 'development' ),
   array( 'typo', 'example.test', 'https://example.test', 'local' ),
   array( '', null, 'https://example.test', 'local' ),
   array( '', null, 'https://example.org', 'production' ),
) as $scenario ) {
   list( $environment, $host, $home_url, $expected ) = $scenario;
   reset_environment( $environment, $host, $home_url );
   do_action( 'init' );
   check( $expected, studiosambal_env_type(), 'Environment: ' . json_encode( $scenario ) );
   $blocked = 'production' !== $expected;
   check( $blocked, false !== has_filter( 'wp_robots' ), 'Robots registration' );
   check( $blocked ? 0 : false, apply_filters( 'pre_option_blog_public', false ), 'Runtime privacy' );
   check( 'local' === $expected && 'valid' === $mode, false !== has_filter( 'wp_get_attachment_url' ), 'Upload hooks' );
   if ( $blocked ) {
      check( "User-agent: Googlebot\nUser-agent: Bingbot\nUser-agent: Applebot\nUser-agent: DuckDuckBot\nDisallow: /wp-content/uploads/\n\nUser-agent: *\nDisallow: /\n",
         apply_filters( 'robots_txt', "User-agent: *\nDisallow: /\n" ), 'robots.txt: zoekmachines lezen noindex, geen uploads, rest niets' );
      check( array( 'noindex' => true, 'nofollow' => true, 'nosnippet' => true, 'noarchive' => true ),
         apply_filters( 'wp_robots', array( 'index' => true, 'follow' => true, 'max-image-preview' => 'large' ) ), 'Core robots' );
      $robots = apply_filters( 'rank_math/frontend/robots', array( 'index' => 'index', 'follow' => 'follow' ) );
      check( 'noindex', $robots['noindex'], 'Rank Math noindex' );
      check( false, isset( $robots['index'] ), 'Rank Math index removed' );
   }
}

// Productie: waarschuwing en adminbalk bij 'Zoekmachines ontmoedigen'.
foreach ( array(
   array( 'production', 'https://example.org', '0', true, 'Productie · niet indexeerbaar', 'studiosambal-env--production studiosambal-env--hidden' ),
   array( 'production', 'https://example.org', '1', false, null, null ),
   array( 'staging', 'https://example.org', '0', false, 'Staging', 'studiosambal-env--staging' ),
   array( 'production', 'https://demo.flywp.xyz', '0', false, 'Staging', 'studiosambal-env--staging' ),
   array( 'local', 'https://example.test', '1', false, 'Lokaal', 'studiosambal-env--local' ),
   array( 'development', 'https://example.org', '1', false, 'Development', 'studiosambal-env--development' ),
) as $scenario ) {
   list( $environment, $home_url, $blog_public, $hidden, $label, $class ) = $scenario;
   reset_environment( $environment, null, $home_url );
   do_action( 'init' );
   check( $hidden, studiosambal_env_production_hidden(), 'Production hidden: ' . json_encode( $scenario ) );
   $is_production = 'production' === studiosambal_env_type();
   check( $is_production, false !== has_action( 'admin_notices', 'studiosambal_env_production_notice' ), 'Production notice registration' );
   check( ! $is_production, false !== has_action( 'admin_notices', 'studiosambal_env_admin_notice' ), 'Blocker notice registration' );
   check( -1, has_action( 'admin_bar_menu', 'studiosambal_env_admin_bar' ), 'Admin bar registration (eerste item)' );
   ob_start();
   studiosambal_env_production_notice();
   check( $hidden, false !== strpos( ob_get_clean(), 'options-reading.php' ), 'Production notice output' );
   $bar = new Fake_Admin_Bar();
   studiosambal_env_admin_bar( $bar );
   check( $label, $bar->nodes['studiosambal-env']['title'] ?? null, 'Admin bar label' );
   check( null === $class ? null : 'studiosambal-env ' . $class, $bar->nodes['studiosambal-env']['meta']['class'] ?? null, 'Admin bar class' );
   check( $hidden, isset( $bar->nodes['studiosambal-env']['href'] ), 'Admin bar link' );
}
$blog_public = '1';

reset_environment( 'production', 'preview.example.org', 'https://preview.example.org' );
// Een gewone plugin kan deze filter na het laden van de mu-plugin toevoegen.
add_filter( 'studiosambal_blocked_domains', function ( $domains ) {
   $domains[] = 'preview.example.org';
   $domains[] = '';
   return $domains;
} );
do_action( 'init' );
check( 'staging', studiosambal_env_type(), 'Late domain filter' );
$upload_dir = array( 'baseurl' => 'https://preview.example.org/app/uploads', 'basedir' => sys_get_temp_dir() );
check( true, false !== strpos( studiosambal_env_robots_txt(), "Disallow: /app/uploads/\n" ), 'robots.txt volgt afwijkende uploadmap' );
$upload_dir = array( 'baseurl' => 'https://example.org/wp-content/uploads', 'basedir' => sys_get_temp_dir() );
check( true, false !== has_filter( 'wp_robots' ), 'Late domain filter activates protection' );

reset_environment( 'local', 'example.test', 'https://example.test' );
do_action( 'init' );
$base = 'https://example.test/wp-content/uploads';
$remote = 'https://remote.example.org/wp-content/uploads';
$fixture = sys_get_temp_dir() . '/sambal-env-' . bin2hex( random_bytes( 6 ) );
mkdir( $fixture );
$upload_dir = array( 'baseurl' => $base, 'basedir' => $fixture );
try {
   if ( 'valid' === $mode ) {
      file_put_contents( $fixture . '/existing.jpg', 'fixture' );
      file_put_contents( $fixture . '/foto café.jpg', 'fixture' );
      foreach ( array( '/existing.jpg', '/foto%20caf%C3%A9.jpg', '/existing.jpg?v=2#view' ) as $suffix ) {
         check( $base . $suffix, apply_filters( 'wp_get_attachment_url', $base . $suffix ), 'Keep existing: ' . $suffix );
      }
      foreach ( array( '/2026/missing.jpg', '/missing.jpg?v=2#view', '/missing%20image.jpg' ) as $suffix ) {
         check( $remote . $suffix, apply_filters( 'wp_get_attachment_url', $base . $suffix ), 'Remote missing: ' . $suffix );
      }
      foreach ( array(
         $base . '-other/image.jpg', $base, $base . '/',
         $base . '/../private.jpg', $base . '/%2e%2e/private.jpg', $base . '/a/./b.jpg',
         $base . '/%00.jpg', $base . '/a%5Cb.jpg', $base . '/a//b.jpg',
         'https://external.example.org/wp-content/uploads/image.jpg',
         'https://example.test:8443/wp-content/uploads/image.jpg',
         'ftp://example.test/wp-content/uploads/image.jpg',
         'https://user:pass@example.test/wp-content/uploads/image.jpg',
         '/wp-content/uploads/image.jpg', false,
      ) as $url ) {
         check( $url, studiosambal_env_remote_upload_url( $url ), 'Unrelated/invalid URL' );
      }
      check( $remote . '/missing.jpg', studiosambal_env_remote_upload_url( 'http://example.test/wp-content/uploads/missing.jpg' ), 'HTTP local variant' );
      check( $remote . '/missing.jpg', studiosambal_env_remote_upload_url( '//example.test/wp-content/uploads/missing.jpg' ), 'Protocol-relative variant' );
      check( array( $remote . '/missing.jpg', 300, 200, true ),
         apply_filters( 'wp_get_attachment_image_src', array( $base . '/missing.jpg', 300, 200, true ) ), 'Image src metadata preserved' );
      check( false, apply_filters( 'wp_get_attachment_image_src', false ), 'Missing attachment' );
      $sources = array( 300 => array( 'url' => $base . '/existing.jpg', 'value' => 300, 'descriptor' => 'w' ),
         600 => array( 'url' => $base . '/missing.jpg', 'value' => 600, 'descriptor' => 'w' ) );
      $expected_sources = $sources;
      $expected_sources[600]['url'] = $remote . '/missing.jpg';
      check( $expected_sources, apply_filters( 'wp_calculate_image_srcset', $sources ), 'Mixed srcset' );
      check( false, apply_filters( 'wp_calculate_image_srcset', false ), 'False srcset' );
      // Een gewijzigde upload-dir (zoals switch_to_blog) mag geen oude cache gebruiken.
      $upload_dir = array( 'baseurl' => 'https://second.test/media', 'basedir' => $fixture . '/second' );
      check( $remote . '/existing.jpg', studiosambal_env_remote_upload_url( 'https://second.test/media/existing.jpg' ), 'Changed site path' );
      check( $base . '/missing.jpg', studiosambal_env_remote_upload_url( $base . '/missing.jpg' ), 'Previous site untouched' );
      // Directe requests naar ontbrekende uploads (hardcoded URL's, CSS).
      $upload_dir = array( 'baseurl' => $base, 'basedir' => $fixture );
      foreach ( array(
         array( 'GET', '/wp-content/uploads/2026/missing.jpg?v=2', $remote . '/2026/missing.jpg?v=2' ),
         array( 'HEAD', '/wp-content/uploads/missing%20image.jpg', $remote . '/missing%20image.jpg' ),
         array( 'GET', '/wp-content/uploads/existing.jpg', '' ),
         array( 'POST', '/wp-content/uploads/missing.jpg', '' ),
         array( 'GET', '/over-ons/', '' ),
         array( 'GET', '/wp-content/uploads-other/missing.jpg', '' ),
         array( 'GET', '/wp-content/uploads/../wp-config.php', '' ),
         array( 'GET', '/wp-content/uploads/%2e%2e/wp-config.php', '' ),
         array( 'GET', '//evil.example.org/wp-content/uploads/missing.jpg', '' ),
         array( 'GET', 'https://evil.example.org/wp-content/uploads/missing.jpg', '' ),
         array( 'GET', '', '' ),
      ) as $request ) {
         $_SERVER['REQUEST_METHOD'] = $request[0];
         $_SERVER['REQUEST_URI'] = $request[1];
         check( $request[2], studiosambal_env_missing_upload_redirect_target(), 'Redirect: ' . json_encode( $request ) );
      }
      unset( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] );

      // SVG-bijlagen die lokaal ontbreken worden één keer opgehaald en opgeslagen.
      $svg = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';
      $ok = array( 'response' => array( 'code' => 200 ), 'body' => $svg );
      $attachments = array(
         1 => array( 'mime' => 'image/svg+xml', 'file' => '2026/02/logo café.svg' ),
         2 => array( 'mime' => 'image/jpeg', 'file' => 'foto.jpg' ),
         3 => array( 'mime' => 'image/svg+xml', 'file' => '2026/02/fout.svg' ),
         4 => array( 'mime' => 'image/svg+xml', 'file' => '2026/02/geen-svg.svg' ),
         5 => array( 'mime' => 'image/svg+xml', 'file' => '../buiten.svg' ),
         6 => array( 'mime' => 'image/svg+xml', 'file' => 'existing.svg' ),
      );
      file_put_contents( $fixture . '/existing.svg', 'lokaal' );
      $http_response = $ok;
      $path = $fixture . '/2026/02/logo café.svg';
      check( $path, apply_filters( 'get_attached_file', $path, 1 ), 'SVG pad ongewijzigd' );
      check( $svg, file_get_contents( $path ), 'SVG lokaal opgeslagen' );
      check( array( $remote . '/2026/02/logo%20caf%C3%A9.svg' ), $http_requests, 'SVG remote URL' );
      apply_filters( 'get_attached_file', $path, 1 );
      apply_filters( 'get_attached_file', $fixture . '/existing.svg', 6 );
      apply_filters( 'get_attached_file', $fixture . '/foto.jpg', 2 );
      check( 1, count( $http_requests ), 'Geen request voor bestaande SVG of andere types' );
      check( 'lokaal', file_get_contents( $fixture . '/existing.svg' ), 'Bestaande SVG niet overschreven' );
      // Pad dat niet bij de bijlage hoort of buiten de uploadmap valt: niets doen.
      apply_filters( 'get_attached_file', $fixture . '/anders.svg', 3 );
      apply_filters( 'get_attached_file', dirname( $fixture ) . '/buiten.svg', 5 );
      check( 1, count( $http_requests ), 'Geen request bij afwijkend of onveilig pad' );
      // Fouten en niet-SVG-antwoorden: niets opslaan, daarna even niet opnieuw proberen.
      $http_response = array( 'response' => array( 'code' => 404 ), 'body' => 'Not found' );
      apply_filters( 'get_attached_file', $fixture . '/2026/02/fout.svg', 3 );
      check( false, file_exists( $fixture . '/2026/02/fout.svg' ), '404 niet opgeslagen' );
      check( 1, $transients['studiosambal_svg_fail_3'] ?? null, '404 onthouden' );
      // Een loginpagina met een SVG-icoon is geen SVG-document.
      $http_response = array( 'response' => array( 'code' => 200 ), 'body' => '<!doctype html><html><body><svg></svg>Inloggen</body></html>' );
      apply_filters( 'get_attached_file', $fixture . '/2026/02/geen-svg.svg', 4 );
      check( false, file_exists( $fixture . '/2026/02/geen-svg.svg' ), 'HTML met SVG-icoon niet opgeslagen' );
      check( 3, count( $http_requests ), 'Requests voor fout en geen-svg' );
      check( array(), glob( $fixture . '/2026/02/*.tmp' ), 'Geen tijdelijke bestanden achtergelaten' );
      // Te groot: niet opslaan.
      $attachments[7] = array( 'mime' => 'image/svg+xml', 'file' => 'groot.svg' );
      $http_response = array( 'response' => array( 'code' => 200 ), 'body' => '<svg>' . str_repeat( ' ', STUDIOSAMBAL_SVG_MAX_BYTES ) . '</svg>' );
      apply_filters( 'get_attached_file', $fixture . '/groot.svg', 7 );
      check( false, file_exists( $fixture . '/groot.svg' ), 'Te grote SVG niet opgeslagen' );
      foreach ( array(
         array( '<svg xmlns="http://www.w3.org/2000/svg"></svg>', true ),
         array( "\xEF\xBB\xBF<?xml version=\"1.0\"?>\n<!-- Illustrator -->\n<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"x\">\n<svg>\n</svg>\n", true ),
         array( '<html><svg></svg></html>', false ),
         array( '<svg></svg><script>x</script>', false ),
         array( '<svg><g>', false ),
         array( '<svgfoo></svgfoo>', false ),
         array( '', false ),
      ) as $case ) {
         check( $case[1], studiosambal_env_is_svg_document( $case[0] ), 'SVG-document: ' . json_encode( $case[0] ) );
      }

      // Origineel ontbreekt, formaat bestaat lokaal: WordPress bouwt het formaat op de
      // (al externe) URL van het origineel; het formaat moet toch lokaal blijven.
      mkdir( $fixture . '/2026/05', 0777, true );
      file_put_contents( $fixture . '/2026/05/foto-300x200.jpg', 'fixture' );
      check( $remote . '/2026/05/foto.jpg', apply_filters( 'wp_get_attachment_url', $base . '/2026/05/foto.jpg' ), 'Origineel extern' );
      check( array( $base . '/2026/05/foto-300x200.jpg', 300, 200, true ),
         apply_filters( 'wp_get_attachment_image_src', array( $remote . '/2026/05/foto-300x200.jpg', 300, 200, true ) ), 'Lokaal formaat blijft lokaal' );
      check( array( $remote . '/2026/05/foto-600x400.jpg', 600, 400, true ),
         apply_filters( 'wp_get_attachment_image_src', array( $remote . '/2026/05/foto-600x400.jpg', 600, 400, true ) ), 'Ontbrekend formaat blijft extern' );
      check( array( 300 => array( 'url' => $base . '/2026/05/foto-300x200.jpg' ), 600 => array( 'url' => $remote . '/2026/05/foto-600x400.jpg' ) ),
         apply_filters( 'wp_calculate_image_srcset', array( 300 => array( 'url' => $remote . '/2026/05/foto-300x200.jpg' ), 600 => array( 'url' => $remote . '/2026/05/foto-600x400.jpg' ) ) ), 'Srcset per formaat' );
      check( 'https://elders.example.org/wp-content/uploads/a.jpg', studiosambal_env_local_upload_url( 'https://elders.example.org/wp-content/uploads/a.jpg' ), 'Andere host niet lokaal gemaakt' );
      check( $remote . 'x/a.jpg', studiosambal_env_local_upload_url( $remote . 'x/a.jpg' ), 'Alleen binnen de externe uploadmap' );
   } else {
      check( '', studiosambal_env_remote_uploads_base(), 'Invalid config disabled' );
      check( $base . '/missing.jpg', apply_filters( 'wp_get_attachment_url', $base . '/missing.jpg' ), 'No remote rewrite' );
      check( $base . '/missing.jpg', studiosambal_env_remote_upload_url( $base . '/missing.jpg' ), 'Direct call without valid config' );
      $_SERVER['REQUEST_URI'] = '/wp-content/uploads/missing.jpg';
      check( '', studiosambal_env_missing_upload_redirect_target(), 'No redirect without valid config' );
      unset( $_SERVER['REQUEST_URI'] );
   }
} finally {
   $items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
   foreach ( $items as $item ) {
      $item->isDir() ? rmdir( $item ) : unlink( $item );
   }
   rmdir( $fixture );
}
echo "OK: $checks checks ($mode)\n";
