(() => {
	const modules = new Map();

	function normalize(specifier) {
		if (typeof specifier !== 'string' || specifier.trim() === '') {
			return null;
		}

		if (!specifier.startsWith('/') || specifier.startsWith('//')) {
			return null;
		}

		let url;

		try {
			url = new URL(specifier, window.location.origin);
		} catch {
			return null;
		}

		if (url.origin !== window.location.origin) {
			return null;
		}

		if (!url.pathname.includes('/assets/app/components/') || !url.pathname.endsWith('.js')) {
			return null;
		}

		return url.pathname + url.search + url.hash;
	}

	function module(specifier) {
		let promise = modules.get(specifier);

		if (!promise) {
			promise = import(specifier);
			modules.set(specifier, promise);
		}

		return promise;
	}

	function enhance(element) {
		const specifier = normalize(element.dataset.module);

		if (specifier === null) {
			element.dataset.moduleError = 'invalid';
			console.error('Invalid panel component module:', element.dataset.module);

			return;
		}

		module(specifier)
			.then(() => {
				element.dataset.moduleReady = 'true';
				delete element.dataset.moduleError;
			})
			.catch(error => {
				element.dataset.moduleError = 'load';
				console.error('Could not load panel component module:', specifier, error);
			});
	}

	function scan(root = document) {
		if (root instanceof Element && root.matches('[data-module]')) {
			enhance(root);
		}

		for (const element of root.querySelectorAll('[data-module]')) {
			enhance(element);
		}
	}

	document.addEventListener('DOMContentLoaded', () => scan());
	document.body.addEventListener('htmx:afterSwap', event => scan(event.target));
})();
