// The session's CSRF token, which the server requires on every unsafe
// request; the document carries it in a meta tag.

export const HEADER = 'X-CSRF-Token';

/** @returns {string} */
export function csrfToken() {
	return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}
