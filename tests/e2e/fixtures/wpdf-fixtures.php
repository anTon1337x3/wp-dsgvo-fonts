<?php
/**
 * Plugin Name: WP DSGVO Fonts – E2E fixtures
 * Description: Embeds Google Fonts and Font Awesome in every way the plugin has to handle.
 */

add_action(
	'wp_enqueue_scripts',
	static function () {
		$dir = content_url( 'mu-plugins/wpdf-fixtures/' );

		// Google Fonts: CSS API v1 (as in the original screenshot), protocol-relative v1 with subset, CSS API v2.
		wp_enqueue_style( 'taptap-fonts', 'https://fonts.googleapis.com/css?family=Montserrat:400,700|Varela+Round|Siliguri:400,600&display=swap', [], '1.0.0' );
		wp_enqueue_style( 'googleOpenSans', '//fonts.googleapis.com/css?family=Open+Sans:400,400italic,600,700,700italic&subset=latin,cyrillic', [], '7.0.7' );
		wp_enqueue_style( 'roboto-css2', 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;1,700&display=swap', [], null );

		// Font Awesome from four different CDNs, one with a subresource integrity hash.
		wp_enqueue_style( 'fa4', 'https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css', [], null );
		wp_enqueue_style( 'fa5', 'https://use.fontawesome.com/releases/v5.15.4/css/all.css', [], null );
		wp_enqueue_style( 'fa6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], null );
		wp_enqueue_style( 'fa6-jsdelivr', 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/brands.min.css', [], null );

		// Font Awesome Kit (fake id: version detection fails and falls back).
		wp_enqueue_script( 'fa-kit', 'https://kit.fontawesome.com/0123456789.js', [], null, false );

		// Web Font Loader from Google's CDN, configured inline.
		wp_enqueue_script( 'webfontloader', 'https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js', [], null, false );
		wp_add_inline_script( 'webfontloader', "WebFont.load({ google: { families: ['Lato:400,700', 'Oswald'] } });" );

		// A theme stylesheet that @imports a local stylesheet, which @imports Google Fonts.
		wp_enqueue_style( 'theme-import', $dir . 'theme.css', [], '1' );

		// Not related to fonts: must stay untouched.
		wp_enqueue_script( 'jquery-cdnjs', 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js', [], null, false );
	}
);

add_filter(
	'style_loader_tag',
	static function ( $tag, $handle ) {
		if ( $handle === 'fa4' ) {
			return str_replace( ' />', ' integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous" />', $tag );
		}

		return $tag;
	},
	10,
	2
);

add_action(
	'wp_head',
	static function () {
		?>
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link rel="preconnect" href="https://use.fontawesome.com">
		<style>@import url('//fonts.googleapis.com/css?family=Pacifico');</style>
		<script>var wpdfFixtureConfig = {"fontUrl":"https:\/\/fonts.googleapis.com\/css?family=Poppins:400&display=swap"};</script>
		<style>
			body { font-family: 'Open Sans', sans-serif; }
			.f-montserrat { font-family: 'Montserrat'; } .f-roboto { font-family: 'Roboto'; } .f-lato { font-family: 'Lato'; }
			.f-pacifico { font-family: 'Pacifico'; } .f-nunito { font-family: 'Nunito'; }
		</style>
		<?php
	},
	1
);

add_action(
	'wp_footer',
	static function () {
		?>
		<div id="wpdf-fixture" style="padding:2em;font-size:22px">
			<p class="f-montserrat">Montserrat <i class="fa fa-home"></i> <i class="fas fa-user"></i> <i class="fa-solid fa-star"></i> <i class="fa-brands fa-github"></i></p>
			<p class="f-roboto">Roboto <em>Italic 700</em></p>
			<p class="f-lato">Lato (Web Font Loader)</p>
			<p class="f-pacifico">Pacifico (inline @import)</p>
			<p class="f-nunito">Nunito (local stylesheet with @import)</p>
			<p class="f-dynamic" style="font-family:'Dancing Script'">Dancing Script (injected by JS, URL localized server-side)</p>
			<p><a href="https://fontawesome.com/icons">Link to the Font Awesome docs stays</a></p>
		</div>
		<script>
			window.addEventListener('load', function () {
				var link = document.createElement('link');
				link.rel = 'stylesheet';
				link.href = 'https://fonts.googleapis.com/css?family=Dancing+Script';
				document.head.appendChild(link);
				window.wpdfDynamicHref = link.getAttribute('href');
			});
		</script>
		<?php
	}
);
