// Unsaved-changes guard for the SSR editor form. The dirty state is
// anchored to the form ELEMENT, not a flag: a swapped-out form is no
// longer in the document, so stale state cannot leak into the next
// page — the failure mode the island version suffered from.

const FORM = '#node-editor-form';

let dirtyForm: HTMLFormElement | null = null;

function isDirty(): boolean {
	return dirtyForm !== null && document.contains(dirtyForm);
}

function mark(event: Event): void {
	const target = event.target;

	if (target instanceof Element) {
		const form = target.closest(FORM);

		if (form instanceof HTMLFormElement) {
			dirtyForm = form;
		}
	}
}

function guard(event: Event): void {
	if (!isDirty()) {
		return;
	}

	// htmx dispatches the event on the request's source element; requests
	// originating inside the form (save, route-path preview) must pass.
	const detail = (event as CustomEvent<{ ctx?: { sourceElement?: Element } }>).detail;
	const source =
		detail?.ctx?.sourceElement ?? (event.target instanceof Element ? event.target : null);

	if (source instanceof Element && source.closest(FORM)) {
		return;
	}

	if (!window.confirm('There are unsaved changes. Leave this editor?')) {
		event.preventDefault();
	}
}

function unload(event: BeforeUnloadEvent): void {
	if (!isDirty()) {
		return;
	}

	event.preventDefault();
	event.returnValue = '';
}

// The save response swaps the status chip out-of-band; a successful
// save marks it data-saved so the guard can stand down.
function settle(): void {
	const status = document.getElementById('editor-status');

	if (status?.dataset.saved === 'true') {
		delete status.dataset.saved;
		dirtyForm = null;
	}
}

export function install(): () => void {
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
