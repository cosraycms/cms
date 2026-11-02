// What a link may point at; src/Richtext/Link.php holds the same rule and
// contract/link-urls.json keeps the two in step.

const SCHEMES = ['http', 'https', 'mailto', 'tel'];

/**
 * Whether an address is relative or uses a scheme that cannot run code in the page.
 *
 * @param {string} href
 * @returns {boolean}
 */
export function safeHref(href) {
	// Browsers ignore spaces and control characters around a URL, and tabs and newlines inside it.
	const scheme = href.replace(/[\u0000- ]+/g, '').match(/^([a-z][a-z0-9+.-]*):/i);

	return scheme === null || SCHEMES.includes(scheme[1].toLowerCase());
}
