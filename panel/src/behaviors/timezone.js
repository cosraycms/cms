import { __ } from '../lib/locale.js';

// Offers the browser's time zone on the user form when it differs from the
// one chosen there, or the site default it stands for. The server cannot
// see the browser's zone, so the panel never switches it on its own.

const SELECT = 'select[data-timezone]';
const BUTTON = '[data-timezone-browser]';

/** @returns {string} */
function browser() {
	return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/**
 * The zone a select stands for, its default option naming the site's.
 *
 * @param {HTMLSelectElement} select
 * @returns {string}
 */
function chosen(select) {
	return select.value || select.options[0]?.dataset.zone || '';
}

/**
 * @param {HTMLSelectElement} select
 */
function sync(select) {
	const button = select.closest('.cms-field')?.querySelector(BUTTON);

	if (!(button instanceof HTMLButtonElement)) {
		return;
	}

	const zone = browser();
	const offered = [...select.options].some((option) => option.value === zone);

	button.textContent = __('user:timezone-browser', { timezone: zone });
	button.hidden = !offered || chosen(select) === zone;
}

function syncAll() {
	document.querySelectorAll(SELECT).forEach((select) => {
		if (select instanceof HTMLSelectElement) {
			sync(select);
		}
	});
}

/**
 * @param {Event} event
 */
function click(event) {
	const button = event.target instanceof Element ? event.target.closest(BUTTON) : null;
	const select = button?.closest('.cms-field')?.querySelector(SELECT);

	if (!(select instanceof HTMLSelectElement)) {
		return;
	}

	select.value = browser();
	select.dispatchEvent(new Event('change', { bubbles: true }));
	select.focus();
}

/**
 * @param {Event} event
 */
function change(event) {
	if (event.target instanceof HTMLSelectElement && event.target.matches(SELECT)) {
		sync(event.target);
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', click);
	document.addEventListener('change', change);
	document.addEventListener('htmx:after:swap', syncAll);
	syncAll();

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('change', change);
		document.removeEventListener('htmx:after:swap', syncAll);
	};
}
