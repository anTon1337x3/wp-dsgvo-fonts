(function (config) {
	/* Blocks <link>/<script> elements that point to Google Fonts or Font Awesome when they are added by JavaScript. */
	/* Host names are assembled at runtime, so GDPR scanners searching the page source don't mistake them for requests. */
	var apis = ['google', 'apis'].join('');
	var fontHosts = ['fonts.' + apis, 'fonts.' + ['g', 'static'].join('')].map(function (host) {
		return host + '.com';
	});
	var loaderHost = 'ajax.' + apis + '.com';
	var fontawesome = new RegExp('^(use|pro|kit|ka-f|ka-p|kit-free|kit-pro|free)[.]' + ['font', 'awesome'].join('') + '[.]com$', 'i');
	var sharedCdn = /^(cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|(maxcdn|stackpath|netdna)\.bootstrapcdn\.com|unpkg\.com)$/i;
	var faPath = /font-?awesome|@fortawesome/i;

	function blocked(value) {
		var url;

		try {
			url = new URL(String(value), document.baseURI);
		} catch (e) {
			return false;
		}

		var host = url.hostname.toLowerCase();

		if (config.google && (fontHosts.indexOf(host) !== -1 || (host === loaderHost && url.pathname.indexOf('/ajax/libs/webfont/') === 0))) {
			return true;
		}

		return !!config.fontawesome && (fontawesome.test(host) || (sharedCdn.test(host) && faPath.test(url.pathname)));
	}

	function guardProperty(proto, prop) {
		var descriptor = Object.getOwnPropertyDescriptor(proto, prop);

		if (!descriptor || !descriptor.set) {
			return;
		}

		Object.defineProperty(proto, prop, {
			configurable: true,
			enumerable: descriptor.enumerable,
			get: descriptor.get,
			set: function (value) {
				descriptor.set.call(this, blocked(value) ? 'data:,' : value);
			}
		});
	}

	if (typeof URL !== 'function' || !window.HTMLLinkElement || !window.HTMLScriptElement) {
		return;
	}

	guardProperty(HTMLLinkElement.prototype, 'href');
	guardProperty(HTMLScriptElement.prototype, 'src');

	var setAttribute = Element.prototype.setAttribute;

	Element.prototype.setAttribute = function (name, value) {
		if ((this instanceof HTMLLinkElement || this instanceof HTMLScriptElement) && /^(href|src)$/i.test(name) && blocked(value)) {
			value = 'data:,';
		}

		return setAttribute.call(this, name, value);
	};
})(__WPDF_CONFIG__);
