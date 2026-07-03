// Submit guard for the SSR editor form. The editor is a single htmx-boosted
// form and the field controls render into its light DOM, so any stray
// <button> without type="button" would default to type="submit" and save the
// node — an accidental, silent write. Only the explicit topbar actions
// (save / publish / preview, marked data-editor-submit) may submit; everything
// else — internal buttons, Enter-to-save — is blocked. This keeps plugin
// element markup safe by default without an audit of every button.

const FORM = 'node-editor-form';

function guard(event: SubmitEvent): void {
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

export function install(): () => void {
	document.addEventListener('submit', guard, true);

	return () => document.removeEventListener('submit', guard, true);
}
