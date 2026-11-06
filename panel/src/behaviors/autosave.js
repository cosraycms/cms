// Keeps the editor's unsaved edits on the server while they are made, so a
// reload, a closed tab or a crash loses nothing, and offers them back when
// the editor opens again. An autosave is not a save: it never changes the
// node, and leaving the editor still asks. The editor page's
// #editor-autosave element says where to send the edits and whether there
// is one to restore; without it, as for a new node, nothing is kept.

import { csrfToken, HEADER, noticeRefusal } from '../lib/csrf.js';
import { nest } from '../lib/form-json.js';
import { __ } from '../lib/locale.js';
import { confirm } from '../lib/modal.js';

const FORM = '#node-editor-form';
const STATE = 'editor-autosave';
const PAUSE = 2000;
const LONGEST = 15000;

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

	try {
		const entries = /** @type {[string, string][]} */ (
			[...new FormData(target)].filter(([, value]) => typeof value === 'string')
		);
		const response = await fetch(element.dataset.url ?? '', {
			method: 'POST',
			credentials: 'same-origin',
			keepalive,
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json',
				'X-Requested-With': 'xmlhttprequest',
				[HEADER]: csrfToken(),
			},
			body: JSON.stringify({ ...nest(entries), _base: element.dataset.base ?? '' }),
		});
		noticeRefusal(response);

		if (response.ok && form === target) {
			kept = Math.max(kept, sent);
		}
	} catch {
		// Offline or refused: the edits stay pending and are tried again.
	} finally {
		sending = false;
	}

	if (!keepalive && form === target && edits !== kept) {
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

	if (form && edits !== kept && !(source instanceof Element && source.closest(FORM))) {
		void send();
	}
}

function pagehide() {
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

		if (edits === kept) {
			clear();
		} else {
			schedule();
		}
	}

	void offer();
}

async function offer() {
	const element = state();
	const url = element?.dataset.restoreUrl;

	if (!element || !url || element.hasAttribute('data-asked')) {
		return;
	}

	element.setAttribute('data-asked', '');
	const stale = element.hasAttribute('data-restore-stale');
	const restore = await confirm({
		title: __('editor:unsaved'),
		message: [
			__('editor:restore-confirm', { time: element.dataset.restoreTime ?? '' }),
			stale ? __('editor:restore-stale') : '',
		]
			.filter(Boolean)
			.join(' '),
		action: __('editor:restore'),
		cancel: __('editor:restore-later'),
		owner: element,
	});

	if (restore && element.isConnected) {
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
	window.addEventListener('pagehide', pagehide);
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
		window.removeEventListener('pagehide', pagehide);
	};
}
