// A form marked data-autosubmit submits as soon as one of its controls
// changes. Without scripts, it needs a submit button of its own.

/**
 * @param {Event} event
 */
function change(event) {
	const control = event.target;

	if (
		(control instanceof HTMLInputElement ||
			control instanceof HTMLSelectElement ||
			control instanceof HTMLTextAreaElement) &&
		control.form?.hasAttribute('data-autosubmit')
	) {
		control.form.requestSubmit();
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('change', change);

	return () => {
		document.removeEventListener('change', change);
	};
}
