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

echo "Homepage:\n";
check( 'no Google Fonts / Font Awesome URLs in the HTML' . ( $leak ? " (found: $leak)" : '' ), $leak === '' );
check( 'host names appear nowhere in the HTML, not even in local paths (scanner simulation)', ! preg_match( '~googleapis|gstatic|\.fontawesome\.com~i', $html ) );
check( 'no preconnect / dns-prefetch to Google, Font Awesome or unused CDNs', ! preg_match( '~rel=.(preconnect|dns-prefetch).[^>]*(gstatic|googleapis|fontawesome|bootstrapcdn|jsdelivr)~i', $html ) );
check( 'dns-prefetch for cdnjs, which is still in use, is kept', (bool) preg_match( '~dns-prefetch[^>]*//cdnjs\.cloudflare\.com~', $html ) );
check( 'no integrity attribute on localized files', ! preg_match( '~dsgvo-fonts[^>]*integrity~', $html ) );
check( 'kit replaced by local Font Awesome Free', strpos( $html, 'wpdf-fa-kit-0-css' ) !== false );
check( 'Web Font Loader uses the custom module', strpos( $html, 'custom:{families:["Lato","Oswald"]' ) !== false );
check( 'JSON-escaped URL replaced', strpos( $html, '"fontUrl":"\/wp-content\/uploads\/dsgvo-fonts' ) !== false );
check( 'JS guard included', strpos( $html, 'id="wpdf-guard"' ) !== false );
check( 'jQuery from cdnjs stays untouched', strpos( $html, 'cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1' ) !== false );
check( 'documentation link to fontawesome.com stays', strpos( $html, 'https://fontawesome.com/icons' ) !== false );
check( 'no blocked (failed) embeddings', strpos( $without_js, 'data:,' ) === false );
check( 'local stylesheet copy with @import served', strpos( $html, '/dsgvo-fonts/local-css/' ) !== false );

echo "Local files:\n";
preg_match_all( '~/wp-content/uploads/dsgvo-fonts/[^"\'\s)\\\\?#]+~', $html, $m );
$queue = array_unique( $m[0] );
$seen  = [];
check( count( $queue ) . ' local files referenced in the HTML', count( $queue ) >= 8 );

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
			check( "$path contains {$found[0]}", false );
		}

		preg_match_all( '~url\("(/wp-content/[^"?#]+)~', $body, $refs );
		array_push( $queue, ...$refs[1] );
	}
}

$fonts = count( preg_grep( '~\.(woff2?|ttf)$~', array_keys( $seen ) ) );
check( count( $seen ) . " files reachable ($fonts font files), no external URLs in CSS", $fonts > 10 );
check( 'no third-party host names in local file paths', ! preg_grep( '~googleapis|gstatic|fontawesome\.com|cdnjs\.cloudflare|jsdelivr\.net|bootstrapcdn\.com|unpkg\.com~i', array_keys( $seen ) ) );

exit( $failed ? 1 : 0 );
