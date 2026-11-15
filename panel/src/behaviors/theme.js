import { remember } from '../lib/panel-cookie.js';

// The masthead's theme toggle. The choice lives in the panel cookie the server
// reads, so a page arrives in its theme. A choice that matches the operating
// system is forgotten, so the panel follows the system again.

const TOGGLE = '[data-theme-toggle]';
const SYSTEM = '(prefers-color-scheme: dark)';

function dark() {
	const theme = document.documentElement.dataset.theme;

	return theme ? theme === 'dark' : matchMedia(SYSTEM).matches;
}

function sync() {
	const pressed = String(dark());

	document.querySelectorAll(TOGGLE).forEach((toggle) => {
		toggle.setAttribute('aria-pressed', pressed);
	});
}

/**
 * @param {Event} event
 */
function click(event) {
	const target = event.target;

	if (!(target instanceof Element) || !target.closest(TOGGLE)) {
		return;
	}

	const theme = dark() ? 'light' : 'dark';
	const system = matchMedia(SYSTEM).matches ? 'dark' : 'light';

	if (theme === system) {
		delete document.documentElement.dataset.theme;
		remember('theme', null);
	} else {
		document.documentElement.dataset.theme = theme;
		remember('theme', theme);
	}

	sync();
}

/** @returns {() => void} */
export function install() {
	const system = matchMedia(SYSTEM);

	document.addEventListener('click', click);
	document.addEventListener('htmx:after:swap', sync);
	system.addEventListener('change', sync);
	sync();

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('htmx:after:swap', sync);
		system.removeEventListener('change', sync);
	};
}
