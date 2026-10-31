import { panelBase } from './runtime.js';

// Per-browser panel choices that change how a page renders live in one cookie
// the server reads, so a page arrives in its state. The server reads only the
// choices src/Panel/Cookie.php lists; a new one goes there as well.

const NAME = 'cosray_panel';
const YEAR = 60 * 60 * 24 * 365;

/** @returns {URLSearchParams} */
function choices() {
	const entry = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${NAME}=`));

	return new URLSearchParams(entry?.slice(NAME.length + 1) ?? '');
}

/**
 * @param {string} key
 * @param {string | null} choice The choice to keep, or null to forget it.
 */
export function remember(key, choice) {
	const params = choices();

	if (choice === null) {
		params.delete(key);
	} else {
		params.set(key, choice);
	}

	const value = params.toString();
	const path = panelBase().replace(/\/$/, '') || '/';

	document.cookie =
		value === ''
			? `${NAME}=; path=${path}; max-age=0; samesite=lax`
			: `${NAME}=${value}; path=${path}; max-age=${YEAR}; samesite=lax`;
}
