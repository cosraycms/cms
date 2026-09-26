// Unsaved-changes guard for the SSR editor form. The dirty state is
// anchored to the form ELEMENT, not a flag: a swapped-out form is no
// longer in the document, so stale state cannot leak into the next
// page — the failure mode the island version suffered from.

const FORM = '#node-editor-form';
const INDICATOR = 'editor-dirty';

/** @type {HTMLFormElement | null} */
let dirtyForm = null;

/** @returns {boolean} */
function isDirty() {
	return dirtyForm !== null && document.contains(dirtyForm);
}

/**
 * @param {boolean} dirty
 */
function indicate(dirty) {
	document.getElementById(INDICATOR)?.toggleAttribute('hidden', !dirty);
}

/**
 * @param {Event} event
 */
function mark(event) {
	const target = event.target;

	if (target instanceof Element && !target.closest('[data-editor-state]')) {
		const form = target.closest(FORM);

		if (form instanceof HTMLFormElement) {
			dirtyForm = form;
			indicate(true);
		}
	}
}

/**
 * @param {Event} event
 */
function guard(event) {
	if (!isDirty()) {
		return;
	}

	// htmx dispatches the event on the request's source element; requests
	// originating inside the form (save, route-path preview) must pass.
	const detail = /** @type {CustomEvent<{ ctx?: { sourceElement?: Element } }>} */ (event).detail;
	const source =
		detail?.ctx?.sourceElement ?? (event.target instanceof Element ? event.target : null);

	// A request marked data-dirty-bypass drops the unsaved edits on purpose
	// (discarding the working copy) and confirms on its own.
	if (source instanceof Element && source.closest(`${FORM}, [data-dirty-bypass]`)) {
		return;
	}

	if (!window.confirm('There are unsaved changes. Leave this editor?')) {
		event.preventDefault();
	}
}

/**
 * @param {BeforeUnloadEvent} event
 */
function unload(event) {
	if (!isDirty()) {
		return;
	}

	event.preventDefault();
	event.returnValue = '';
}

// The save response swaps the status chip out-of-band; a successful
// save marks it data-saved so the guard can stand down.
function settle() {
	const status = document.getElementById('editor-status');

	if (status?.dataset.saved === 'true') {
		delete status.dataset.saved;
		dirtyForm = null;
		indicate(false);
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('input', mark);
	document.addEventListener('change', mark);
	document.addEventListener('cosray-change', mark);
	document.addEventListener('htmx:before:request', guard);
	document.addEventListener('htmx:after:swap', settle);
	window.addEventListener('beforeunload', unload);

	return () => {
		document.removeEventListener('input', mark);
		document.removeEventListener('change', mark);
		document.removeEventListener('cosray-change', mark);
		document.removeEventListener('htmx:before:request', guard);
		document.removeEventListener('htmx:after:swap', settle);
		window.removeEventListener('beforeunload', unload);
	};
}
