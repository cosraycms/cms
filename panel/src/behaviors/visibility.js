// The editor's publication and listing switches act at once: a change submits
// their own form, outside the editor form, so the save does not wait for it.

const FORM = 'node-editor-visibility';

/**
 * @param {Event} event
 */
function change(event) {
	const target = event.target;

	if (target instanceof HTMLInputElement && target.form?.id === FORM) {
		target.form.requestSubmit();
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('change', change);

	return () => document.removeEventListener('change', change);
}
