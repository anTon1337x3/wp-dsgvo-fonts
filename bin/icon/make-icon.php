<?php
/**
 * Generates the WordPress.org plugin icon as SVG: "Gg" on a white tile with a rainbow border.
 *
 *   php bin/icon/make-icon.php <glyphs.json> > icon.svg
 *
 * The JSON file comes from glyphs.py: "Gg" set in Figtree (SIL Open Font License) as path data.
 */

$glyphs = json_decode( file_get_contents( $argv[1] ), true );

// Center the bounding box of "Gg" on the tile; the gradient spans both letters.
$tx = round( 128 - ( $glyphs['x'] + $glyphs['width'] / 2 ), 2 );
$ty = round( 128 - ( $glyphs['y'] + $glyphs['height'] / 2 ), 2 );
$x1 = round( $glyphs['x'], 2 );
$y1 = round( $glyphs['y'], 2 );
$x2 = round( $glyphs['x'] + $glyphs['width'], 2 );
$y2 = round( $glyphs['y'] + $glyphs['height'], 2 );

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">
	<defs>
		<linearGradient id="rainbow" x1="0" y1="0" x2="1" y2="1">
			<stop offset="0" stop-color="#a78bfa" />
			<stop offset="0.18" stop-color="#f472b6" />
			<stop offset="0.36" stop-color="#fb923c" />
			<stop offset="0.52" stop-color="#facc15" />
			<stop offset="0.68" stop-color="#4ade80" />
			<stop offset="0.84" stop-color="#22d3ee" />
			<stop offset="1" stop-color="#6366f1" />
		</linearGradient>
		<linearGradient id="letters" gradientUnits="userSpaceOnUse" x1="$x1" y1="$y1" x2="$x2" y2="$y2">
			<stop offset="0" stop-color="#14b8a6" />
			<stop offset="0.5" stop-color="#3b82f6" />
			<stop offset="1" stop-color="#8b5cf6" />
		</linearGradient>
		<filter id="glow" x="-30%" y="-30%" width="160%" height="160%">
			<feGaussianBlur stdDeviation="9" />
		</filter>
	</defs>
	<rect x="30" y="30" width="196" height="196" rx="54" fill="none" stroke="url(#rainbow)" stroke-width="14" opacity="0.45" filter="url(#glow)" />
	<rect x="30" y="30" width="196" height="196" rx="54" fill="#ffffff" />
	<rect x="30" y="30" width="196" height="196" rx="54" fill="none" stroke="url(#rainbow)" stroke-width="7" />
	<path transform="translate($tx $ty)" fill="url(#letters)" d="{$glyphs['d']}" />
</svg>

SVG;
