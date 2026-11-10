// Keeps the editor's unsaved edits on the server while they are made, so a
// reload, a closed tab or a crash loses nothing, and offers to show or
// discard them when the editor opens again. An autosave is not a save: it
// never changes the node, and leaving the editor still asks. The editor
// page's #editor-autosave element says where to send the edits and whether
// there is one to restore; without it, as for a new node, nothing is kept.

import { csrfToken, HEADER, noticeRefusal } from '../lib/csrf.js';
import { nest } from '../lib/form-json.js';
import { __ } from '../lib/locale.js';
import { button, frame, openModal } from '../lib/modal.js';

const FORM = '#node-editor-form';
const STATE = 'editor-autosave';
// Dispatched on the document as the autosave moves: an edit waits for it,
// it is sent, the server kept or dropped it, or it failed and is retried.
export const EVENT = 'cosray-autosave';

/** @typedef {'pending' | 'saving' | 'kept' | 'dropped' | 'failed'} Autosave */
// A form that drops the autosave, so its pending edits must not outlive it.
const DROP = '[data-autosave-drop]';
const PAUSE = 2000;
const LONGEST = 15000;
// Browsers refuse a keepalive request whose body exceeds 64 KiB.
const KEEPALIVE_BYTES = 60000;

// Counters of the form they belong to, so a swapped-in editor starts over.
/** @type {HTMLFormElement | null} */
let form = null;
let edits = 0;
let kept = 0;
let submitted = 0;
let sending = false;
/** @type {ReturnType<typeof setTimeout> | undefined} */
let pause;
/** @type {ReturnType<typeof setTimeout> | undefined} */
let longest;

/**
 * @param {Autosave} autosave
 */
function report(autosave) {
	document.dispatchEvent(new CustomEvent(EVENT, { detail: { state: autosave } }));
}

/** @returns {HTMLElement | null} */
function state() {
	return document.getElementById(STATE);
}

/**
 * Whether the server holds every edit the editor shows, so leaving the
 * page loses nothing. Always false where the editor keeps no autosave.
 *
 * @returns {boolean}
 */
export function autosaved() {
	const current = document.querySelector(FORM);

	return state() !== null && current !== null && (current !== form || edits === kept);
}

function clear() {
	clearTimeout(pause);
	clearTimeout(longest);
	pause = undefined;
	longest = undefined;
}

function schedule() {
	clearTimeout(pause);
	pause = setTimeout(() => void send(), PAUSE);
	longest ??= setTimeout(() => void send(), LONGEST);
}

/**
 * @param {Event} event
 */
function mark(event) {
	const target = event.target;

	if (!(target instanceof Element) || target.closest('[data-editor-state]') || !state()) {
		return;
	}

	const edited = target.closest(FORM);

	if (!(edited instanceof HTMLFormElement)) {
		return;
	}

	if (edited !== form) {
		form = edited;
		edits = kept = submitted = 0;
		clear();
	}

	edits++;
	schedule();
	report('pending');
}

/**
 * @param {boolean} [keepalive] Outlives the page, for its last edits.
 */
async function send(keepalive = false) {
	clear();
	const element = state();
	const target = form;

	if (!element || !target?.isConnected || edits === kept) {
		return;
	}

	if (sending) {
		schedule();

		return;
	}

	const sent = edits;
	sending = true;
	report('saving');

	try {
		const entries = /** @type {[string, string][]} */ (
			[...new FormData(target)].filter(([, value]) => typeof value === 'string')
		);
		const body = JSON.stringify({ ...nest(entries), _base: element.dataset.base ?? '' });
		const response = await fetch(element.dataset.url ?? '', {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: keepalive && new TextEncoder().encode(body).length <= KEEPALIVE_BYTES,
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json',
				'X-Requested-With': 'xmlhttprequest',
				[HEADER]: csrfToken(),
			},
			body,
		});
		noticeRefusal(response);

		if (response.ok && form === target) {
			kept = Math.max(kept, sent);
			/** @type {{ kept?: boolean }} */
			const result = await response.json().catch(() => ({}));

			if (edits === kept) {
				report(result.kept === false ? 'dropped' : 'kept');
			}
		} else if (form === target) {
			report('failed');
		}
	} catch {
		// Offline or refused: the edits stay pending and are tried again.
		if (form === target) {
			report('failed');
		}
	} finally {
		sending = false;
	}

	if (form === target && edits !== kept) {
		longest ??= setTimeout(() => void send(), LONGEST);
	}
}

/**
 * @param {SubmitEvent} event
 */
function submit(event) {
	if (event.target === form) {
		submitted = edits;
	}
}

/**
 * Sends what is pending before a request takes the editor away.
 *
 * @param {Event} event
 */
function leave(event) {
	const ctx = /** @type {CustomEvent<{ ctx?: { sourceElement?: Element } }>} */ (event).detail?.ctx;
	const source = ctx?.sourceElement ?? (event.target instanceof Element ? event.target : null);

	if (!form || edits === kept || (source instanceof Element && source.closest(FORM))) {
		return;
	}

	if (source instanceof Element && source.closest(DROP)) {
		kept = edits;
		clear();
	} else {
		void send();
	}
}

/**
 * A hidden tab may be discarded without a pagehide, as on mobile.
 *
 * @param {Event} event
 */
function hide(event) {
	if (event.type === 'visibilitychange' && document.visibilityState !== 'hidden') {
		return;
	}

	if (form && edits !== kept) {
		void send(true);
	}
}

// A save swaps the state in again, marked, with the base it wrote; the
// server dropped the autosave, so only edits made since the submit remain.
function settle() {
	const element = state();

	if (element?.hasAttribute('data-saved')) {
		element.removeAttribute('data-saved');
		kept = Math.max(kept, submitted);
		document.getElementById('editor-restored')?.remove();
		document.getElementById('editor-autosave-discard')?.remove();
		document.querySelectorAll('[data-restored-mark]').forEach((mark) => mark.remove());

		if (edits === kept) {
			clear();
		} else {
			schedule();
		}
	}

	void offer();
}

/**
 * Asks what becomes of the autosave. Discard drops it, Show loads it into
 * the editor; closing the dialog leaves it for the next time.
 *
 * @param {HTMLElement} owner
 * @param {string} message
 * @returns {Promise<'show' | 'discard' | null>}
 */
function ask(owner, message) {
	return new Promise((resolve) => {
		/** @type {'show' | 'discard' | null} */
		let answer = null;
		/** @param {'show' | 'discard'} choice */
		const choose = (choice) => () => {
			answer = choice;
			handle.close();
		};
		const handle = openModal(
			(host) => {
				const discard = button(__('editor:restore-discard'), 'danger', choose('discard'));
				const show = button(__('editor:restore'), 'primary', choose('show'));
				show.dataset.dialogFocus = '';
				const text = document.createElement('p');
				text.className = 'question';
				text.textContent = message;
				frame(host, __('editor:unsaved'), [text], [discard, show]).classList.add('cms-confirm');

				return () => resolve(answer);
			},
			{ owner, size: 'compact' },
		);
	});
}

/**
 * @param {string} url
 */
async function discard(url) {
	if (!url) {
		return;
	}

	try {
		noticeRefusal(
			await fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'xmlhttprequest',
					[HEADER]: csrfToken(),
				},
			}),
		);
	} catch {
		// Offline: the autosave stays and is offered again.
	}
}

async function offer() {
	const element = state();
	const url = element?.dataset.restoreUrl;

	if (!element || !url || element.hasAttribute('data-asked')) {
		return;
	}

	element.setAttribute('data-asked', '');
	const stale = element.hasAttribute('data-restore-stale');
	const answer = await ask(
		element,
		[
			__('editor:restore-confirm', { time: element.dataset.restoreTime ?? '' }),
			stale ? __('editor:restore-stale') : '',
		]
			.filter(Boolean)
			.join(' '),
	);

	if (!element.isConnected) {
		return;
	}

	if (answer === 'discard') {
		await discard(element.dataset.discardUrl ?? '');
	} else if (answer === 'show') {
		await htmx.ajax('GET', url, {
			target: '#main',
			swap: 'innerHTML show:top',
			replace: location.pathname + location.search,
		});
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('input', mark);
	document.addEventListener('change', mark);
	document.addEventListener('cosray-change', mark);
	document.addEventListener('submit', submit, true);
	document.addEventListener('htmx:before:request', leave);
	document.addEventListener('htmx:after:swap', settle);
	document.addEventListener('visibilitychange', hide);
	window.addEventListener('pagehide', hide);
	void offer();

	return () => {
		clear();
		form = null;
		edits = kept = submitted = 0;
		document.removeEventListener('input', mark);
		document.removeEventListener('change', mark);
		document.removeEventListener('cosray-change', mark);
		document.removeEventListener('submit', submit, true);
		document.removeEventListener('htmx:before:request', leave);
		document.removeEventListener('htmx:after:swap', settle);
		document.removeEventListener('visibilitychange', hide);
		window.removeEventListener('pagehide', hide);
	};
}
