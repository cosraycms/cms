// Submit guard for the SSR editor form. The editor is a single htmx-boosted
// form and the field controls render into its light DOM, so any stray
// <button> without type="button" would default to type="submit" and save the
// node — an accidental, silent write. Only the explicit topbar actions
// (save / publish / preview, marked data-editor-submit) may submit; everything
// else — internal buttons, Enter in a field — is blocked. This keeps plugin
// element markup safe by default without an audit of every button.

const FORM = 'node-editor-form';

// Input types whose Enter activates the control itself instead of
// submitting the form.
const ACTIVATED = new Set(['button', 'submit', 'reset', 'image', 'file', 'color']);

/**
 * @param {SubmitEvent} event
 */
function guard(event) {
	const form = event.target;

	if (!(form instanceof HTMLFormElement) || form.id !== FORM) {
		return;
	}

	const submitter = event.submitter;

	if (submitter instanceof HTMLElement && submitter.hasAttribute('data-editor-submit')) {
		return;
	}

	// Runs in the capture phase, before htmx's boost handler sees the submit.
	event.preventDefault();
	event.stopImmediatePropagation();
}

/**
 * Enter in a field submits through the form's default button, the first
 * explicit action in tree order, so the submit itself looks like a click
 * on that action and has to be stopped at the key.
 *
 * @param {KeyboardEvent} event
 */
function implicit(event) {
	const field = event.target;

	if (event.key !== 'Enter' || event.isComposing || !(field instanceof HTMLInputElement)) {
		return;
	}

	if (field.form?.id === FORM && !ACTIVATED.has(field.type)) {
		event.preventDefault();
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('submit', guard, true);
	document.addEventListener('keydown', implicit);

	return () => {
		document.removeEventListener('submit', guard, true);
		document.removeEventListener('keydown', implicit);
	};
}
