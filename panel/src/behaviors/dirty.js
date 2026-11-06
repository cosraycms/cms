// Unsaved-changes guard for the SSR editor form. The dirty state is
// anchored to the form ELEMENT, not a flag: a swapped-out form is no
// longer in the document, so stale state cannot leak into the next
// page — the failure mode the island version suffered from.

/** @import { Context } from './dialogs.js' */

import { __ } from '../lib/locale.js';
import { autosaved } from './autosave.js';
import { ask } from './dialogs.js';

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
	const ctx = /** @type {CustomEvent<{ ctx?: Context }>} */ (event).detail?.ctx;

	if (!ctx || !isDirty()) {
		return;
	}

	// htmx dispatches the event on the request's source element; requests
	// originating inside the form (save, route-path preview) must pass.
	const source = ctx.sourceElement ?? (event.target instanceof Element ? event.target : null);

	// A request marked data-dirty-bypass does not leave the editor, like the
	// media library in a dialog, or drops the unsaved edits on purpose, like
	// discarding the working copy, and confirms on its own. A request with a
	// question of its own keeps it, so it is not asked away.
	if (
		(source instanceof Element && source.closest(`${FORM}, [data-dirty-bypass]`)) ||
		ctx.confirm
	) {
		return;
	}

	ask(ctx, {
		title: __('editor:unsaved'),
		message: __(
			document.getElementById('editor-autosave') ? 'editor:leave-kept' : 'editor:leave-confirm',
		),
		action: __('editor:leave'),
		tone: 'danger',
	});
}

/**
 * @param {BeforeUnloadEvent} event
 */
function unload(event) {
	// Edits the server holds as an autosave come back when the editor opens again.
	if (!isDirty() || autosaved()) {
		return;
	}

	event.preventDefault();
	event.returnValue = '';
}

// An editor restored from an autosave shows edits nobody saved yet.
function restored() {
	const state = document.querySelector('#editor-autosave[data-restored]');
	const form = document.querySelector(FORM);

	if (state && form instanceof HTMLFormElement) {
		state.removeAttribute('data-restored');
		dirtyForm = form;
		indicate(true);
	}
}

// The save response swaps the status chip out-of-band; a successful
// save marks it data-saved so the guard can stand down.
function settle() {
	restored();

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
	document.addEventListener('htmx:config:request', guard);
	document.addEventListener('htmx:after:swap', settle);
	window.addEventListener('beforeunload', unload);
	restored();

	return () => {
		document.removeEventListener('input', mark);
		document.removeEventListener('change', mark);
		document.removeEventListener('cosray-change', mark);
		document.removeEventListener('htmx:config:request', guard);
		document.removeEventListener('htmx:after:swap', settle);
		window.removeEventListener('beforeunload', unload);
	};
}
