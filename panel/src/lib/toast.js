// The panel's toasts: short notes about events that have no place of their
// own on the page, like a finished upload or a refused action. They gather
// in the document's #cms-toasts live region. A server response adds one out
// of band as a plain `<div data-toast="error">message</div>` appended to the
// region; install() turns those into toasts after every swap. A full page
// load leaves them in their out-of-band wrapper, which install() empties
// into the region. A modal dialog makes the region inert, so toasts raised
// while one is open wait until the last one closes.

import { __ } from './locale.js';

const REGION = 'cms-toasts';
const KINDS = ['success', 'info', 'warning', 'error'];
const LINGER = 5000;

/** @typedef {'success' | 'info' | 'warning' | 'error'} Kind */

/** @returns {HTMLElement} */
function region() {
	let element = document.getElementById(REGION);

	if (!element) {
		element = document.createElement('div');
		element.id = REGION;
		element.className = 'cms-toasts';
		element.setAttribute('role', 'region');
		element.setAttribute('aria-label', __('common:notifications'));
		element.setAttribute('aria-live', 'polite');
		document.body.append(element);
	}

	return element;
}

/** @type {HTMLElement[]} */
const held = [];

/** @type {HTMLElement | null} */
let returnTo = null;

// Every panel dialog opens modal, and jsdom cannot match :modal.
function blocked() {
	return document.querySelector('dialog[open]') !== null;
}

/**
 * Where focus goes when the focused toast closes: the next toast, else the
 * place it came from.
 *
 * @param {HTMLElement} item
 */
function dismiss(item) {
	const focused = item.contains(document.activeElement);
	const items = Array.from(region().querySelectorAll('.cms-toasts-item'));
	const index = items.indexOf(item);
	const next = items[index + 1] ?? items[index - 1];

	item.remove();

	if (!focused) {
		return;
	}

	const target = next?.querySelector('button') ?? returnTo;

	if (target instanceof HTMLElement && target.isConnected) {
		target.focus({ preventScroll: true });
	}
}

/**
 * Hides the toast after a while, but not while the pointer or focus is on
 * it, so it can be read to the end.
 *
 * @param {HTMLElement} item
 */
function linger(item) {
	/** @type {ReturnType<typeof setTimeout> | undefined} */
	let timer;
	const start = () => {
		clearTimeout(timer);
		timer = setTimeout(() => item.remove(), LINGER);
	};
	const stop = () => clearTimeout(timer);

	item.addEventListener('pointerenter', stop);
	item.addEventListener('pointerleave', start);
	item.addEventListener('focusin', stop);
	item.addEventListener('focusout', start);
	start();
}

/**
 * @param {HTMLElement} item
 */
function upgrade(item) {
	const kind = /** @type {Kind} */ (
		KINDS.includes(item.dataset.toast ?? '') ? item.dataset.toast : 'info'
	);
	const message = document.createElement('p');
	message.className = 'cms-toasts-message';
	message.textContent = (item.textContent ?? '').trim();
	const close = document.createElement('button');
	close.type = 'button';
	close.className = 'cms-toasts-close';
	close.setAttribute('aria-label', __('common:close'));
	close.textContent = '×';
	close.addEventListener('click', () => dismiss(item));

	delete item.dataset.toast;
	item.className = `cms-toasts-item is-${kind}`;
	item.replaceChildren(message, close);

	// Errors and warnings stay until closed and interrupt; the rest is polite.
	if (kind === 'error' || kind === 'warning') {
		item.setAttribute('role', 'alert');
	} else {
		linger(item);
	}
}

/**
 * @param {Kind} kind
 * @param {string} message
 */
export function toast(kind, message) {
	const item = document.createElement('div');
	item.dataset.toast = kind;
	item.textContent = message;
	show(item);
}

/** @param {HTMLElement} item */
function show(item) {
	if (blocked()) {
		item.remove();
		held.push(item);

		return;
	}

	region().append(item);
	upgrade(item);
}

function settle() {
	for (const item of document.querySelectorAll(`#${REGION} > [data-toast]`)) {
		if (item instanceof HTMLElement) {
			show(item);
		}
	}
}

function release() {
	if (!blocked()) {
		held.splice(0).forEach(show);
	}
}

/** @param {FocusEvent} event */
function remember(event) {
	const from = event.relatedTarget;
	const to = event.target;

	if (
		to instanceof Node &&
		region().contains(to) &&
		from instanceof HTMLElement &&
		!region().contains(from)
	) {
		returnTo = from;
	}
}

/** @returns {() => void} */
export function install() {
	for (const wrapper of document.querySelectorAll(`[hx-swap-oob="beforeend:#${REGION}"]`)) {
		region().append(...wrapper.children);
		wrapper.remove();
	}

	settle();
	document.addEventListener('htmx:after:swap', settle);
	// close does not bubble.
	document.addEventListener('close', release, true);
	document.addEventListener('focusin', remember);

	return () => {
		document.removeEventListener('htmx:after:swap', settle);
		document.removeEventListener('close', release, true);
		document.removeEventListener('focusin', remember);
		held.length = 0;
		returnTo = null;
	};
}
