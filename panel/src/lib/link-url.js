// What a link may point at; src/LinkTarget.php holds the same rule and
// contract/link-urls.json keeps the two in step.

const SCHEMES = ['http', 'https', 'mailto', 'tel'];

/** What an editor can add to the `rel` of an address, also in contract/link-rels.json. */
export const RELS = ['nofollow', 'sponsored', 'noreferrer'];

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
