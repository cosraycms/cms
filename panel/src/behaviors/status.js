// The node editor's status between saves. The server renders the stages
// (dots in the head, the pipeline in the inspector) with every page and
// save; in between, the user's own edits move through client states the
// server never sees: saving while an autosave is pending or in flight,
// kept once the server holds it, error when it failed. The head's buttons
// follow: saving the draft needs edits of the user's, applying edits or a
// draft. Forms marked data-done-toast say they succeeded in a toast, which
// outlives the editor they reload.

/** @import { Context } from './dialogs.js' */

/** @import { Autosave } from './autosave.js' */

import { __ } from '../lib/locale.js';
import { toast } from '../lib/toast.js';
import { EVENT } from './autosave.js';

const DOTS = 'editor-status-dots';
const PANEL = 'editor-status-panel';

/** @returns {HTMLElement | null} */
function dots() {
	return document.getElementById(DOTS);
}

/**
 * @param {string} state
 * @returns {string}
 */
function text(state) {
	switch (state) {
		case 'saving':
			return __('status:mine-saving');
		case 'error':
			return __('status:mine-error');
		case 'conflict':
			return __('status:mine-conflict');
		case 'present':
			return __('status:mine-present', {
				time: new Date().toLocaleTimeString(document.documentElement.lang || undefined, {
					hour: '2-digit',
					minute: '2-digit',
				}),
			});
		default:
			return __('status:mine-none');
	}
}

/**
 * Moves the mine stage to a state in the head and the inspector.
 *
 * @param {string} state
 */
function show(state) {
	const head = dots();
	const label = text(state);

	document
		.querySelectorAll(`#${DOTS} [data-stage="mine"], #${PANEL} [data-stage="mine"]`)
		.forEach((element) => {
			if (element instanceof HTMLElement && element.dataset.state !== undefined) {
				element.dataset.state = state;
			}
		});
	document
		.querySelectorAll(
			`#${DOTS} .row[data-stage="mine"] .text, #${PANEL} [data-stage="mine"] [data-stage-text]`,
		)
		.forEach((element) => {
			element.textContent = label;
		});
	document
		.querySelector(`#${PANEL} [data-mine-actions]`)
		?.toggleAttribute('hidden', state === 'none');

	if (head) {
		const rows = Array.from(head.querySelectorAll('.row'), (row) =>
			[
				row.querySelector('strong')?.textContent ?? '',
				row.querySelector('.text')?.textContent ?? '',
			].join(' – '),
		);
		head.setAttribute('aria-label', __('status:label', { status: rows.join('; ') }));
	}

	follow();
}

/**
 * Enables saving for edits of the user's, and offers to apply them, with a
 * draft, to the main version. An unpublished node with nothing to apply
 * offers to publish instead. Edits on an outdated state are saved only
 * anyway, never applied.
 */
function follow() {
	const head = dots();

	if (!head) {
		return;
	}

	const mine = head.querySelector('[data-stage="mine"]');
	const state = mine instanceof HTMLElement ? (mine.dataset.state ?? 'none') : 'none';
	const pending = state !== 'none' || head.dataset.draft === 'true';
	const conflict = head.dataset.mine === 'conflict' && state !== 'none';
	const save = document.querySelector('[data-needs="mine"]');
	const label = save?.querySelector('[data-save-label]');

	if (save instanceof HTMLButtonElement) {
		save.disabled = state === 'none';
		save.name = conflict ? 'force' : '';
		save.value = conflict ? '1' : '';
	}

	if (label instanceof HTMLElement) {
		label.textContent =
			(conflict ? label.dataset.conflict : label.dataset.normal) ?? label.textContent;
	}

	const apply = document.getElementById('editor-apply');
	const published = apply?.dataset.published === 'true';

	apply?.querySelectorAll('[data-apply-when]').forEach((button) => {
		if (!(button instanceof HTMLButtonElement)) {
			return;
		}

		if (button.dataset.applyWhen === 'idle') {
			button.hidden = pending;
		} else {
			button.hidden = !pending && !published;
			button.disabled = !pending || conflict;
		}
	});
}

/**
 * A switch moved the node's change time; an editor that was current
 * follows, so its next save is no conflict with itself.
 */
function rebase() {
	const rebased = document.getElementById('editor-rebase');
	const state = document.getElementById('editor-autosave');
	const { from, to } = rebased?.dataset ?? {};

	if (!rebased || !state || !from || !to) {
		return;
	}

	if (state.dataset.base === from) {
		state.dataset.base = to;
		const input = state.querySelector('input[name="_base"]');

		if (input instanceof HTMLInputElement) {
			input.value = to;
		}
	}

	delete rebased.dataset.from;
	delete rebased.dataset.to;
}

function settle() {
	rebase();
	follow();
}

/**
 * @param {Event} event
 */
function autosave(event) {
	const head = dots();
	const state = /** @type {CustomEvent<{ state: Autosave }>} */ (event).detail?.state;

	if (!head || !state) {
		return;
	}

	// An autosave keeps the base it started from: edits on an older state stay a conflict.
	const kept = head.dataset.mine === 'conflict' ? 'conflict' : 'present';

	show(
		{ pending: 'saving', saving: 'saving', kept, dropped: 'none', failed: 'error' }[state] ??
			'none',
	);
}

/**
 * @param {Event} event
 */
function done(event) {
	const ctx =
		/** @type {CustomEvent<{ ctx?: Context & { request?: { method?: string }, response?: { status: number } } }>} */ (
			event
		).detail?.ctx;
	const source = ctx?.sourceElement;
	const message = source instanceof HTMLElement ? source.dataset.doneToast : undefined;
	const post = ctx?.request?.method?.toUpperCase() === 'POST';

	// The editor the form reloads names the form as its source too.
	if (message && post && ctx?.response && ctx.response.status < 400) {
		toast('success', message);
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener(EVENT, autosave);
	document.addEventListener('htmx:after:swap', settle);
	document.addEventListener('htmx:after:request', done);
	follow();

	return () => {
		document.removeEventListener(EVENT, autosave);
		document.removeEventListener('htmx:after:swap', settle);
		document.removeEventListener('htmx:after:request', done);
	};
}
