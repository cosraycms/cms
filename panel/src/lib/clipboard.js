// Copies text. Without a secure context, as on a plain http development
// host, there is no clipboard API, so a selected scratch field and the copy
// command stand in.

/**
 * @param {string} value
 * @returns {boolean}
 */
function fallback(value) {
	const field = document.createElement('textarea');
	field.value = value;
	field.readOnly = true;
	field.style.position = 'fixed';
	field.style.opacity = '0';
	document.body.append(field);
	field.select();

	try {
		return document.execCommand('copy');
	} catch {
		return false;
	} finally {
		field.remove();
	}
}

/**
 * @param {string} value
 * @returns {Promise<boolean>} Whether the text reached the clipboard.
 */
export async function copyText(value) {
	try {
		await navigator.clipboard.writeText(value);

		return true;
	} catch {
		return fallback(value);
	}
}
