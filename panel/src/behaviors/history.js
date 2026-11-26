// Keeps the address of a form that answers in place out of the history.
//
// htmx 4 pushes the URL of every boosted request, so a boosted form that
// posts and stays on the page shows its action in the address bar, which
// a reload then asks for with a GET that has no route. A request other
// than a GET pushes only when it was redirected, to a page a GET loads.
// The element's hx-push-url or hx-replace-url, and the response's
// HX-Push-Url or HX-Replace-Url, still decide when they are given.

/**
 * @typedef {object} HistoryContext
 * @property {unknown} [push]
 * @property {unknown} [replace]
 * @property {{ method?: string }} [request]
 * @property {{ raw?: { redirected?: boolean } }} [response]
 */

/**
 * @param {Event} event
 */
function stay(event) {
	const ctx = /** @type {CustomEvent<{ ctx?: HistoryContext }>} */ (event).detail?.ctx;

	if (
		!ctx ||
		ctx.request?.method === 'GET' ||
		ctx.push != null ||
		ctx.replace != null ||
		ctx.response?.raw?.redirected
	) {
		return;
	}

	ctx.push = 'false';
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('htmx:before:response', stay);

	return () => document.removeEventListener('htmx:before:response', stay);
}
