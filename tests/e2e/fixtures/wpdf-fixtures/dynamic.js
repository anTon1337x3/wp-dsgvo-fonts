/* Inserts fonts as HTML strings at runtime. An external file, so the server-side rewrite can't see the URLs. */
jQuery(function ($) {
	var family = 'Lobster';

	// Same pattern as Divi's sticky-elements.js.
	$('head').append('<link id="wpdf-jquery-append" href="//fonts.googleapis.com/css?family=' + family + '" rel="stylesheet" type="text/css" />');

	document.head.insertAdjacentHTML('beforeend', '<link id="wpdf-adjacent-html" rel="stylesheet" href="https://fonts.googleapis.com/css?family=Bangers&amp;display=swap">');

	var container = document.createElement('div');
	document.body.appendChild(container);
	container.innerHTML = '<link id="wpdf-inner-html" rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/v4-shims.css">';

	var template = document.createElement('template');
	template.innerHTML = '<script id="wpdf-template-script" src="https://kit.fontawesome.com/abcdef.js"></script>';
	document.head.append(template.content.cloneNode(true));

	// Unrelated CDN file: must still load.
	$('head').append('<link id="wpdf-unrelated" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">');

	window.wpdfDynamicDone = true;
});
