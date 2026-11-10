// The panel's toasts: short notes about events that have no place of their
// own on the page, like a finished upload or a refused action. They gather
// in the document's #cms-toasts live region. A server response adds one out
// of band as a plain `<div data-toast="error">message</div>` appended to the
// region; install() turns those into toasts after every swap. A full page
// load leaves them in their out-of-band wrapper, which install() empties
// into the region.

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
		element.setAttribute('aria-live', 'polite');
		document.body.append(element);
	}

	return element;
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
	message.className = 'message';
	message.textContent = (item.textContent ?? '').trim();
	const close = document.createElement('button');
	close.type = 'button';
	close.className = 'close';
	close.setAttribute('aria-label', __('common:close'));
	close.textContent = '×';
	close.addEventListener('click', () => item.remove());

	delete item.dataset.toast;
	item.className = `toast is-${kind}`;
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
	region().append(item);
	upgrade(item);
}

function settle() {
	for (const item of document.querySelectorAll(`#${REGION} > [data-toast]`)) {
		if (item instanceof HTMLElement) {
			upgrade(item);
		}
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

	return () => document.removeEventListener('htmx:after:swap', settle);
}
