// Sends the CSRF token with every unsafe htmx request.

import { csrfToken, HEADER } from '../lib/csrf.js';

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

/** @returns {() => void} */
export function install() {
	const events = new AbortController();
	const options = { signal: events.signal };

	document.addEventListener('htmx:config:request', attach, options);

	return () => events.abort();
}
