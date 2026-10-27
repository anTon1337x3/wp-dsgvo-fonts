<?php
/**
 * Checks the fixture site's homepage and every local copy it references.
 *
 *   php check.php http://localhost:8089
 */

$site      = rtrim( $argv[1] ?? 'http://localhost:8089', '/' );
$forbidden = '~//(fonts\.googleapis\.com|fonts\.gstatic\.com|ajax\.googleapis\.com|(use|pro|kit|ka-f|ka-p)\.fontawesome\.com|[a-z.]*bootstrapcdn\.com/font-awesome|cdnjs\.cloudflare\.com/ajax/libs/font-awesome|cdn\.jsdelivr\.net/npm/@fortawesome)~i';
$failed    = false;

function get( $url, &$code = null ) {
	$context = stream_context_create( [ 'http' => [ 'ignore_errors' => true, 'timeout' => 120 ] ] );
	$body    = file_get_contents( $url, false, $context );
	$code    = (int) explode( ' ', $http_response_header[0] ?? 'HTTP/1.1 0' )[1];

	return (string) $body;
}

function check( $label, $ok ) {
	global $failed;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $label . "\n";
	$failed = $failed || ! $ok;
}

$html       = get( $site . '/' );
$without_js = preg_replace( '~<script id="wpdf-guard">.*?</script>~s', '', $html );

$leak = preg_match( $forbidden, $html, $m ) ? $m[0] : '';

echo "Startseite:\n";
check( 'keine Google-Fonts-/Font-Awesome-URLs im HTML' . ( $leak ? " (gefunden: $leak)" : '' ), $leak === '' );
check( 'keine preconnect/dns-prefetch zu Google/Font Awesome/ungenutzten CDNs', ! preg_match( '~rel=.(preconnect|dns-prefetch).[^>]*(gstatic|googleapis|fontawesome|bootstrapcdn|jsdelivr)~i', $html ) );
check( 'dns-prefetch für weiterhin genutztes cdnjs bleibt', (bool) preg_match( '~dns-prefetch[^>]*//cdnjs\.cloudflare\.com~', $html ) );
check( 'kein integrity-Attribut an lokalisierter Datei', ! preg_match( '~wp-dsgvo-fonts[^>]*integrity~', $html ) );
check( 'Kit durch lokale Font Awesome Free ersetzt', strpos( $html, 'wpdf-fa-kit-0-css' ) !== false );
check( 'Web Font Loader nutzt custom-Modul', strpos( $html, 'custom:{families:["Lato","Oswald"]' ) !== false );
check( 'JSON-escaped URL ersetzt', strpos( $html, '"fontUrl":"\/wp-content\/uploads\/wp-dsgvo-fonts' ) !== false );
check( 'JS-Schutz eingebunden', strpos( $html, 'id="wpdf-guard"' ) !== false );
check( 'jQuery von cdnjs bleibt unverändert', strpos( $html, 'cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1' ) !== false );
check( 'Doku-Link zu fontawesome.com bleibt', strpos( $html, 'https://fontawesome.com/icons' ) !== false );
check( 'keine blockierten (fehlgeschlagenen) Einbindungen', strpos( $without_js, 'data:,' ) === false );
check( 'lokale CSS-Kopie mit @import ausgeliefert', strpos( $html, '/wp-dsgvo-fonts/local-css/' ) !== false );

echo "Lokale Dateien:\n";
preg_match_all( '~/wp-content/uploads/wp-dsgvo-fonts/[^"\'\s)\\\\?#]+~', $html, $m );
$queue = array_unique( $m[0] );
$seen  = [];
check( count( $queue ) . ' lokale Dateien im HTML referenziert', count( $queue ) >= 8 );

while ( $queue ) {
	$path = array_shift( $queue );

	if ( isset( $seen[ $path ] ) ) {
		continue;
	}

	$seen[ $path ] = true;
	$body          = get( $site . $path, $code );

	if ( $code !== 200 ) {
		check( "$path → HTTP $code", false );
		continue;
	}

	if ( substr( $path, -4 ) === '.css' ) {
		if ( preg_match( $forbidden, $body, $found ) ) {
			check( "$path enthält {$found[0]}", false );
		}

		preg_match_all( '~url\("(/wp-content/[^"?#]+)~', $body, $refs );
		array_push( $queue, ...$refs[1] );
	}
}

$fonts = count( preg_grep( '~\.(woff2?|ttf)$~', array_keys( $seen ) ) );
check( count( $seen ) . " Dateien abrufbar (davon $fonts Schriftdateien), keine externen URLs in CSS", $fonts > 10 );

exit( $failed ? 1 : 0 );
