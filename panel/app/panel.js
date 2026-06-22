(() => {
	const mainSelector = '#main';

	const currentPath = () => window.location.pathname.replace(/\/$/, '') || '/';

	const linkPath = link => {
		try {
			return new URL(link.href, window.location.href).pathname.replace(/\/$/, '') || '/';
		} catch {
			return '';
		}
	};

	const updateNavigation = () => {
		const path = currentPath();

		document.querySelectorAll('.nav-link[aria-current]').forEach(link => {
			link.removeAttribute('aria-current');
		});

		document.querySelectorAll('.nav-link[href]').forEach(link => {
			if (linkPath(link) === path) {
				link.setAttribute('aria-current', 'page');
			}
		});
	};

	const focusSearch = event => {
		if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) {
			return;
		}

		const target = event.target;

		if (
			target instanceof HTMLInputElement ||
			target instanceof HTMLTextAreaElement ||
			target instanceof HTMLSelectElement ||
			target?.isContentEditable
		) {
			return;
		}

		const search = document.querySelector('.search input[type="search"]');

		if (search instanceof HTMLInputElement) {
			event.preventDefault();
			search.focus();
			search.select();
		}
	};

	document.addEventListener('keydown', focusSearch);
	document.body.addEventListener('htmx:afterSwap', event => {
		if (event.detail?.target?.matches?.(mainSelector)) {
			updateNavigation();
		}
	});
	document.body.addEventListener('htmx:pushedIntoHistory', updateNavigation);
})();
