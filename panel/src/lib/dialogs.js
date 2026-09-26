import { __ } from './locale.js';

/**
 * @typedef {object} Options
 * @property {HTMLElement} [owner]
 * @property {HTMLElement | null} [opener]
 * @property {() => void} [onClose]
 */

/**
 * @typedef {object} Dialog
 * @property {() => void} close
 * @property {HTMLElement} [owner]
 * @property {HTMLElement | null} opener
 */

/** @type {WeakMap<HTMLDialogElement, Dialog>} */
const dialogs = new WeakMap();
let titleId = 0;

/**
 * @param {HTMLElement} element
 * @returns {boolean}
 */
function usable(element) {
	return (
		element.isConnected &&
		!element.matches(':disabled') &&
		!element.closest('[hidden], [inert]') &&
		element.checkVisibility({ visibilityProperty: true })
	);
}

/** @param {HTMLDialogElement} dialog */
export function closeDialog(dialog) {
	const active = dialogs.get(dialog);
	if (active) active.close();
	else if (dialog.open) dialog.close();
}

/**
 * @param {HTMLDialogElement} dialog
 * @param {Options} [options]
 * @returns {{ close(): void }}
 */
export function openDialog(dialog, options = {}) {
	const existing = dialogs.get(dialog);
	if (existing && dialog.open) return existing;
	existing?.close();

	const active = document.activeElement;
	const opener =
		options.opener === undefined
			? active instanceof HTMLElement && active !== document.body
				? active
				: null
			: options.opener;
	const owner = options.owner ?? opener ?? undefined;
	const events = new AbortController();
	let finished = false;
	/** @type {number | null} */
	let backdropPointer = null;

	const observer = new MutationObserver(() => {
		if (!dialog.isConnected || (owner && !owner.isConnected)) close();
	});

	function close() {
		if (finished) return;
		finished = true;
		observer.disconnect();
		events.abort();
		dialogs.delete(dialog);

		try {
			// Dynamic child dialogs stay in body, not inside their parent dialog.
			for (const child of Array.from(document.querySelectorAll('dialog[open]')).reverse()) {
				if (!(child instanceof HTMLDialogElement) || child === dialog) continue;
				const nested = dialogs.get(child);
				if (
					nested &&
					((nested.owner && dialog.contains(nested.owner)) ||
						(nested.opener && dialog.contains(nested.opener)))
				)
					nested.close();
			}
		} finally {
			if (dialog.open) dialog.close();
			if (opener && usable(opener)) opener.focus({ preventScroll: true });
			options.onClose?.();
		}
	}

	/**
	 * @param {PointerEvent} event
	 * @returns {boolean}
	 */
	function backdrop(event) {
		if (event.target !== dialog) return false;
		const box = dialog.getBoundingClientRect();
		return (
			event.clientX < box.left ||
			event.clientX > box.right ||
			event.clientY < box.top ||
			event.clientY > box.bottom
		);
	}

	dialog.addEventListener(
		'cancel',
		(event) => {
			event.preventDefault();
			event.stopPropagation();
			close();
		},
		{ signal: events.signal },
	);
	dialog.addEventListener(
		'close',
		() => {
			if (!dialog.open) close();
		},
		{ signal: events.signal },
	);
	dialog.addEventListener('keydown', (event) => event.stopPropagation(), { signal: events.signal });
	dialog.addEventListener('keyup', (event) => event.stopPropagation(), { signal: events.signal });
	dialog.addEventListener(
		'click',
		(event) => {
			if (
				event.target instanceof Element &&
				event.target.closest('[data-dialog-close]')?.closest('dialog') === dialog
			)
				close();
		},
		{ signal: events.signal },
	);
	dialog.addEventListener(
		'pointerdown',
		(event) => {
			backdropPointer = event.button === 0 && backdrop(event) ? event.pointerId : null;
		},
		{ signal: events.signal },
	);
	dialog.addEventListener(
		'pointerup',
		(event) => {
			const dismiss = backdropPointer === event.pointerId && backdrop(event);
			backdropPointer = null;
			if (dismiss) close();
		},
		{ signal: events.signal },
	);
	dialog.addEventListener(
		'pointercancel',
		() => {
			backdropPointer = null;
		},
		{ signal: events.signal },
	);
	window.addEventListener('pagehide', close, { signal: events.signal });

	if (!dialog.hasAttribute('aria-label') && !dialog.hasAttribute('aria-labelledby')) {
		const heading = dialog.querySelector('[data-dialog-title], h1, h2, h3');
		if (heading) {
			heading.id ||= `cms-dialog-title-${++titleId}`;
			dialog.setAttribute('aria-labelledby', heading.id);
		} else {
			dialog.setAttribute('aria-label', __('common:dialog'));
		}
	}

	const handle = { close, owner, opener };
	if (owner && !owner.isConnected) {
		close();
		return handle;
	}
	dialogs.set(dialog, handle);
	observer.observe(document.documentElement, { childList: true, subtree: true });

	try {
		if (dialog.open) dialog.close();
		dialog.showModal();
		const focus = [
			...dialog.querySelectorAll('[data-dialog-focus], [autofocus]'),
			...dialog.querySelectorAll('input:not([type="hidden"]):not([type="file"]), textarea, select'),
		]
			.filter((element) => element instanceof HTMLElement)
			.find(usable);
		focus?.focus({ preventScroll: true });
	} catch (error) {
		close();
		throw error;
	}

	return handle;
}
