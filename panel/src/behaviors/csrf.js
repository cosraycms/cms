// Sends the CSRF token with every unsafe htmx request. A refusal of a
// request that carried it means the page is out of date: the user signed in
// or out in another tab, or the session expired. The dialog says so instead
// of reloading, so unsaved edits can still be copied.

import { openDialog } from '../lib/dialogs.js';
import { csrfToken, EVENT, HEADER } from '../lib/csrf.js';

const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

/**
 * @param {Event} event
 */
function attach(event) {
	const detail =
		/** @type {CustomEvent<{ ctx?: { request?: { method?: string, headers?: Record<string, string> } } }>} */ (
			event
		).detail;
	const request = detail?.ctx?.request;

	if (!request || SAFE_METHODS.has((request.method ?? 'GET').toUpperCase())) {
		return;
	}

	request.headers = { ...request.headers, [HEADER]: csrfToken() };
}

function expired() {
	const dialog = document.querySelector('dialog[data-csrf-expired]');

	if (dialog instanceof HTMLDialogElement) {
		openDialog(dialog);
	}
}

/**
 * @param {Event} event
 */
function reload(event) {
	if (event.target instanceof Element && event.target.closest('[data-csrf-reload]')) {
		window.location.reload();
	}
}

/** @returns {() => void} */
export function install() {
	const events = new AbortController();
	const options = { signal: events.signal };

	document.addEventListener('htmx:config:request', attach, options);
	document.addEventListener(EVENT, expired, options);
	document.addEventListener('click', reload, options);

	return () => events.abort();
}
