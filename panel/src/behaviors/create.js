// A collection's create links open a dialog with the new entry's required
// fields instead of the whole editor; creating it opens the editor. The
// links keep their address, so a new tab still gets the full editor. While
// a required native field is empty, the dialog's create button waits; the
// server checks the rest.

/** @import { Context } from './dialogs.js' */

import { openModal } from '../lib/modal.js';

const LINK = 'a[data-create-dialog]';
const FIELDS = 'input[required], textarea[required], select[required]';

/**
 * @param {HTMLFormElement} form
 */
function ready(form) {
	const button = form.querySelector('button[type="submit"]');

	if (button instanceof HTMLButtonElement) {
		button.disabled = Array.from(form.querySelectorAll(FIELDS)).some(
			(field) =>
				(field instanceof HTMLInputElement ||
					field instanceof HTMLTextAreaElement ||
					field instanceof HTMLSelectElement) &&
				!field.disabled &&
				field.value.trim() === '',
		);
	}
}

/**
 * @param {HTMLElement} host
 */
function loaded(host) {
	const form = host.querySelector('form[data-create]');

	if (!(form instanceof HTMLFormElement)) {
		return;
	}

	ready(form);
	form.addEventListener('input', () => ready(form));
	const first = form.querySelector(
		'.cms-node-sheet :is(input, textarea, select):not([type="hidden"])',
	);

	if (first instanceof HTMLElement) {
		first.focus();
	}
}

/**
 * @param {MouseEvent} event
 */
function click(event) {
	if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
		return;
	}

	const link = event.target instanceof Element ? event.target.closest(LINK) : null;

	if (!(link instanceof HTMLAnchorElement)) {
		return;
	}

	event.preventDefault();
	const url = new URL(link.href);
	url.pathname = `${url.pathname.replace(/\/$/, '')}/dialog`;
	/** @type {{ close(): void } | undefined} */
	let handle;

	handle = openModal(
		(host) => {
			/** @param {Event} swap */
			const swapped = (swap) => {
				const ctx = /** @type {CustomEvent<{ ctx?: { target?: Element } }>} */ (swap).detail?.ctx;

				if (ctx?.target === host) {
					loaded(host);
				}
			};
			// A created entry opens the editor; the dialog goes first, so
			// its form does not count as unsaved edits on the way.
			/** @param {Event} request */
			const done = (request) => {
				const ctx =
					/** @type {CustomEvent<{ ctx?: Context & { response?: { headers?: Headers } } }>} */ (
						request
					).detail?.ctx;
				const source = ctx?.sourceElement;

				if (
					source instanceof Element &&
					host.contains(source) &&
					ctx?.response?.headers?.get('HX-Location')
				) {
					handle?.close();
				}
			};

			document.addEventListener('htmx:after:swap', swapped);
			document.addEventListener('htmx:after:request', done);
			void htmx.ajax('GET', url.pathname + url.search, { target: host, swap: 'innerHTML' });

			return () => {
				document.removeEventListener('htmx:after:swap', swapped);
				document.removeEventListener('htmx:after:request', done);
			};
		},
		{ hideClose: true, label: link.textContent?.trim() ?? '' },
	);
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', click);

	return () => document.removeEventListener('click', click);
}
