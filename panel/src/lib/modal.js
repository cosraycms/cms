/** @import { ModalOptions } from '../types/bridge' */

import { openDialog } from './dialogs.js';
import { icon } from './icons.js';
import { __ } from './locale.js';

/**
 * A question for confirm(). The action labels the confirming button with a
 * verb; it titles the dialog too when there is no title. `cancel` labels
 * the other button when Cancel does not fit.
 *
 * @typedef {object} Question
 * @property {string} message
 * @property {string} [title]
 * @property {string} [action]
 * @property {string} [cancel]
 * @property {'primary' | 'danger'} [tone]
 * @property {HTMLElement} [owner]
 */

/**
 * Mounts the content a renderer builds in a dialog created for it and
 * removed again on closure. The renderer may return a cleanup function.
 *
 * @param {(host: HTMLElement) => (() => void) | void} render
 * @param {ModalOptions} [options]
 * @returns {{ close(): void }}
 */
export function openModal(render, options = {}) {
	const active = document.activeElement;
	const opener = active instanceof HTMLElement && active !== document.body ? active : null;
	const dialog = document.createElement('dialog');
	dialog.className = 'cms-modal';
	if (options.label !== undefined) dialog.setAttribute('aria-label', options.label);
	if (options.size) dialog.dataset.size = options.size;

	if (!options.hideClose) {
		const close = document.createElement('button');
		close.type = 'button';
		close.className = 'modal-close';
		close.dataset.dialogClose = '';
		close.setAttribute('aria-label', __('common:close'));
		close.innerHTML = icon('x-lg');
		dialog.append(close);
	}

	const host = document.createElement('div');
	host.className = 'element';
	dialog.append(host);
	document.body.append(dialog);
	/** @type {(() => void) | void} */
	let cleanup;

	try {
		cleanup = render(host);
	} catch (error) {
		dialog.remove();
		throw error;
	}

	return openDialog(dialog, {
		opener,
		owner: options.owner ?? opener ?? document.getElementById('cosray-system-data') ?? undefined,
		onClose: () => {
			try {
				cleanup?.();
			} finally {
				dialog.remove();
			}
		},
	});
}

/**
 * A footer button. The confirming action is `primary`, or `danger solid`
 * when it destroys something; dismissing is `secondary`.
 *
 * @param {string} label
 * @param {string} variant Space-separated variant names without their prefix.
 * @param {() => void} [onclick]
 * @returns {HTMLButtonElement}
 */
export function button(label, variant, onclick) {
	const element = document.createElement('button');
	element.type = 'button';
	element.className = [
		'cms-button',
		...variant.split(' ').map((name) => `cms-button-${name}`),
	].join(' ');
	element.textContent = label;
	if (onclick) element.addEventListener('click', onclick);

	return element;
}

/**
 * Fills a modal host with the titled header, the body and, given buttons,
 * the footer, and returns the body.
 *
 * @param {HTMLElement} host
 * @param {string} title
 * @param {Node[]} body
 * @param {HTMLElement[]} [footer]
 * @returns {HTMLElement}
 */
export function frame(host, title, body, footer) {
	const header = document.createElement('header');
	header.className = 'modal-header';
	const heading = document.createElement('h2');
	heading.className = 'modal-title';
	heading.dataset.dialogTitle = '';
	heading.textContent = title;
	header.append(heading);
	const main = document.createElement('div');
	main.className = 'modal-body';
	main.append(...body);
	host.append(header, main);

	if (footer) {
		const foot = document.createElement('footer');
		foot.className = 'modal-footer';
		foot.append(...footer);
		host.append(foot);
	}

	return main;
}

/**
 * Asks before an action. Cancel, the close button, Escape and the backdrop
 * answer no, as does the owner leaving the document.
 *
 * @param {Question} question
 * @returns {Promise<boolean>}
 */
export function confirm({ message, title, action, cancel: dismiss, tone = 'primary', owner }) {
	return new Promise((resolve) => {
		let confirmed = false;
		const label = action ?? __('common:ok');
		const handle = openModal(
			(host) => {
				const cancel = button(dismiss ?? __('common:cancel'), 'secondary', () => handle.close());
				const ok = button(label, tone === 'danger' ? 'danger solid' : 'primary', () => {
					confirmed = true;
					handle.close();
				});
				// A destructive action must not be one Enter away.
				(tone === 'danger' ? cancel : ok).dataset.dialogFocus = '';
				const text = document.createElement('p');
				text.className = 'question';
				text.textContent = message;
				frame(host, title ?? label, [text], [cancel, ok]).classList.add('cms-confirm');

				return () => resolve(confirmed);
			},
			{ owner, size: 'compact' },
		);
	});
}

/**
 * Tells something that needs no decision.
 *
 * @param {{ title: string, message: string, owner?: HTMLElement }} notice
 * @returns {Promise<void>}
 */
export function alert({ title, message, owner }) {
	return new Promise((resolve) => {
		const handle = openModal(
			(host) => {
				const ok = button(__('common:ok'), 'primary', () => handle.close());
				ok.dataset.dialogFocus = '';
				const text = document.createElement('p');
				text.textContent = message;
				frame(host, title, [text], [ok]);

				return () => resolve();
			},
			{ owner, size: 'compact' },
		);
	});
}

/**
 * The question an element asks: its message, with the title, action and
 * tone from its `data-confirm-title`, `data-confirm-action` and
 * `data-confirm-tone` attributes.
 *
 * @param {Element} element
 * @param {string} message
 * @returns {Question}
 */
export function question(element, message) {
	const tone = element.getAttribute('data-confirm-tone');

	return {
		message,
		title: element.getAttribute('data-confirm-title') ?? undefined,
		action: element.getAttribute('data-confirm-action') ?? undefined,
		tone: tone === 'danger' ? 'danger' : 'primary',
		owner: element instanceof HTMLElement ? element : undefined,
	};
}
