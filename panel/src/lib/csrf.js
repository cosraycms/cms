// The session's CSRF token, which the server requires on every unsafe
// request; the document carries it in a meta tag.

export const HEADER = 'X-CSRF-Token';
export const EVENT = 'cosray:csrf-expired';

/** @returns {string} */
export function csrfToken() {
	return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Raises the refusal event for a fetch() response that is the server's
 * refusal of the token; htmx raises it from the HX-Trigger header itself.
 * The event's dialog reports the refusal, so a caller need not.
 *
 * @param {Response} response
 * @returns {boolean} Whether the response was such a refusal.
 */
export function noticeRefusal(response) {
	if (response.status === 403 && response.headers.get('HX-Trigger') === EVENT) {
		document.dispatchEvent(new CustomEvent(EVENT));

		return true;
	}

	return false;
}
