// The node editor's status between saves. The server renders the stages
// (dots in the head, the pipeline in the inspector) with every page and
// save; in between, the user's own edits move through client states the
// server never sees: saving while an autosave is pending or in flight,
// kept once the server holds it, error when it failed. The head's buttons
// and the pipeline's steps follow: saving the draft needs edits of the
// user's, applying edits or a draft. Forms marked data-done-toast say they
// succeeded in a toast, which outlives the editor they reload.

/** @import { Context } from './dialogs.js' */

/** @import { Autosave } from './autosave.js' */

import { __ } from '../lib/locale.js';
import { toast } from '../lib/toast.js';
import { EVENT } from './autosave.js';

const DOTS = 'editor-status-dots';
const PANEL = 'editor-status-panel';
const APPLY = 'editor-apply-confirm';
const DISCARD = 'node-editor-discard';

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
		.querySelectorAll(
			`#${DOTS} [data-stage="mine"], #${PANEL} [data-stage="mine"], #${APPLY} [data-stage="mine"]`,
		)
		.forEach((element) => {
			if (element instanceof HTMLElement && element.dataset.state !== undefined) {
				element.dataset.state = state;
			}
		});
	document
		.querySelectorAll(
			`#${DOTS} .row[data-stage="mine"] .text, #${PANEL} [data-stage="mine"] [data-stage-text], #${APPLY} [data-confirm-stage="mine"] [data-stage-text]`,
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
	const draft = head.dataset.draft === 'true';
	const pending = state !== 'none' || draft;
	const conflict = head.dataset.mine === 'conflict' && state !== 'none';

	document.querySelectorAll('[data-needs="mine"]').forEach((save) => {
		if (!(save instanceof HTMLButtonElement)) {
			return;
		}

		save.disabled = state === 'none';
		save.name = conflict ? 'force' : '';
		save.value = conflict ? '1' : '';
		const label = save.querySelector('[data-save-label]');

		if (label instanceof HTMLElement) {
			label.textContent =
				(conflict ? label.dataset.conflict : label.dataset.normal) ?? label.textContent;
		}
	});

	const apply = document.getElementById('editor-apply');
	const published = apply?.dataset.published === 'true';

	apply?.querySelectorAll('[data-apply-when]').forEach((element) => {
		if (element instanceof HTMLElement) {
			element.hidden = element.dataset.applyWhen === 'idle' ? pending : !pending && !published;
		}
	});
	document
		.querySelectorAll(`#editor-apply [data-apply-button], #${PANEL} [data-step="apply"] button`)
		.forEach((button) => {
			if (button instanceof HTMLButtonElement) {
				button.disabled = !pending || conflict;
			}
		});
	document.querySelectorAll(`#${APPLY} [data-confirm-stage]`).forEach((item) => {
		if (item instanceof HTMLElement) {
			item.hidden = item.dataset.confirmStage === 'mine' ? state === 'none' : !draft;
		}
	});

	const panel = document.getElementById(PANEL);
	const step = panel?.querySelector('[data-step="apply"]');

	if (step instanceof HTMLElement) {
		step.hidden = !pending;
		const help = step.querySelector('[data-step-help]');

		if (help instanceof HTMLElement) {
			const caption = draft && state !== 'none' ? 'both' : draft ? 'draft' : 'mine';
			help.textContent = help.dataset[caption] ?? help.textContent;
		}
	}

	const save = panel?.querySelector('[data-step="save"]');

	if (save instanceof HTMLElement) {
		save.hidden = state === 'none';
	}

	discardMineToo(state);
}

/**
 * Discarding the draft offers to keep the user's edits while there are
 * some. Kept, their pending part goes out with the request.
 *
 * @param {string} state
 */
function discardMineToo(state) {
	const option = document.querySelector('[data-discard-mine]');
	const form = document.getElementById(DISCARD);

	if (option instanceof HTMLElement) {
		option.hidden = state === 'none';
	}

	const box = option?.querySelector('input');

	form?.toggleAttribute('data-autosave-drop', !(box instanceof HTMLInputElement) || box.checked);
}

/**
 * @param {Event} event
 */
function change(event) {
	if (event.target instanceof Element && event.target.closest('[data-discard-mine]')) {
		follow();
	}
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
	document.addEventListener('change', change);
	document.addEventListener('htmx:after:swap', follow);
	document.addEventListener('htmx:after:request', done);
	follow();

	return () => {
		document.removeEventListener(EVENT, autosave);
		document.removeEventListener('change', change);
		document.removeEventListener('htmx:after:swap', follow);
		document.removeEventListener('htmx:after:request', done);
	};
}
