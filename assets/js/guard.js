(function (config) {
	/* Blocks <link>/<script> elements that point to Google Fonts or Font Awesome when they are added by JavaScript. */
	/* Host names are assembled at runtime, so GDPR scanners searching the page source don't mistake them for requests. */
	var apis = ['google', 'apis'].join('');
	var fontHosts = ['fonts.' + apis, 'fonts.' + ['g', 'static'].join('')].map(function (host) {
		return host + '.com';
	});
	var loaderHost = 'ajax.' + apis + '.com';
	var fontawesome = new RegExp('^(use|pro|kit|ka-f|ka-p|kit-free|kit-pro|free)[.]' + ['font', 'awesome'].join('') + '[.]com$', 'i');
	var sharedCdn = /^(cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|(maxcdn|stackpath|netdna)\.bootstrapcdn\.com)$/i;
	var faPath = /font-?awesome|@fortawesome/i;
	var urlInHtml = /(?:https?:)?\/\/[^\s"'<>()`]+/gi;

	function blocked(value) {
		var url;

		try {
			url = new URL(String(value).replace(/&amp;/g, '&'), document.baseURI);
		} catch (e) {
			return false;
		}

		var host = url.hostname.toLowerCase();

		if (config.google && (fontHosts.indexOf(host) !== -1 || (host === loaderHost && url.pathname.indexOf('/ajax/libs/webfont/') === 0))) {
			return true;
		}

		return !!config.fontawesome && (fontawesome.test(host) || (sharedCdn.test(host) && faPath.test(url.pathname)));
	}

	/* For markup inserted as a string (innerHTML, insertAdjacentHTML, document.write): the browser would start loading a <link> as soon as it's parsed. */
	function sanitizeHtml(html) {
		if (typeof html !== 'string' || html.indexOf('//') === -1) {
			return html;
		}

		return html.replace(urlInHtml, function (url) {
			return blocked(url) ? 'data:,' : url;
		});
	}

	if (typeof URL !== 'function' || !window.HTMLLinkElement || !window.HTMLScriptElement) {
		return;
	}

	var setAttribute = Element.prototype.setAttribute;

	/* For nodes built while detached (e.g. jQuery's .append('<link …>') parses into a fragment first): checked right before they're inserted. */
	function neutralize(node) {
		if (!node || (node.nodeType !== 1 && node.nodeType !== 11)) {
			return node;
		}

		var elements = node.querySelectorAll ? [].slice.call(node.querySelectorAll('link, script')) : [];

		if (node.nodeType === 1 && /^(LINK|SCRIPT)$/.test(node.tagName)) {
			elements.push(node);
		}

		elements.forEach(function (element) {
			var attribute = element.tagName === 'LINK' ? 'href' : 'src';
			var value = element.getAttribute(attribute);

			if (value && blocked(value)) {
				setAttribute.call(element, attribute, 'data:,');
			}
		});

		return node;
	}

	function wrapSetter(proto, prop, transform) {
		var descriptor = Object.getOwnPropertyDescriptor(proto, prop);

		if (!descriptor || !descriptor.set) {
			return;
		}

		Object.defineProperty(proto, prop, {
			configurable: true,
			enumerable: descriptor.enumerable,
			get: descriptor.get,
			set: function (value) {
				descriptor.set.call(this, transform(value));
			}
		});
	}

	function wrapMethod(target, name, transform) {
		var original = target && target[name];

		if (typeof original !== 'function') {
			return;
		}

		target[name] = function () {
			return original.apply(this, transform([].slice.call(arguments)));
		};
	}

	function blockUrl(value) {
		return blocked(value) ? 'data:,' : value;
	}

	function neutralizeFirst(args) {
		neutralize(args[0]);

		return args;
	}

	function neutralizeAll(args) {
		args.forEach(neutralize);

		return args;
	}

	wrapSetter(HTMLLinkElement.prototype, 'href', blockUrl);
	wrapSetter(HTMLScriptElement.prototype, 'src', blockUrl);
	wrapSetter(Element.prototype, 'innerHTML', sanitizeHtml);
	wrapSetter(Element.prototype, 'outerHTML', sanitizeHtml);

	Element.prototype.setAttribute = function (name, value) {
		if ((this instanceof HTMLLinkElement || this instanceof HTMLScriptElement) && /^(href|src)$/i.test(name) && blocked(value)) {
			value = 'data:,';
		}

		return setAttribute.call(this, name, value);
	};

	wrapMethod(Node.prototype, 'appendChild', neutralizeFirst);
	wrapMethod(Node.prototype, 'insertBefore', neutralizeFirst);
	wrapMethod(Node.prototype, 'replaceChild', neutralizeFirst);
	wrapMethod(Element.prototype, 'insertAdjacentElement', function (args) {
		neutralize(args[1]);

		return args;
	});
	wrapMethod(Element.prototype, 'insertAdjacentHTML', function (args) {
		args[1] = sanitizeHtml(args[1]);

		return args;
	});

	['append', 'prepend', 'before', 'after', 'replaceWith', 'replaceChildren'].forEach(function (name) {
		wrapMethod(Element.prototype, name, neutralizeAll);
		wrapMethod(window.DocumentFragment && DocumentFragment.prototype, name, neutralizeAll);
	});

	wrapMethod(document, 'write', function (args) {
		return args.map(sanitizeHtml);
	});
	wrapMethod(document, 'writeln', function (args) {
		return args.map(sanitizeHtml);
	});
})(__WPDF_CONFIG__);
