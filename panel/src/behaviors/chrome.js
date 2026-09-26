import { openDialog } from '../lib/dialogs.js';

// Editor chrome: the preview overlay's close button and the meta
// dialogs. The overlay anchor is only emptied, never removed —
// out-of-band swaps need the id to stay in the document. A meta button
// opens the dialog of its nearest owner (the field wrapper, or a block
// row inside a blocks field), so a row's gear never reaches the dialog
// of the field around it.

/**
 * @param {Event} event
 */
function onClick(event) {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const close = target.closest('[data-overlay-close]');

	if (close) {
		const overlay = document.getElementById('editor-preview');

		if (overlay) {
			overlay.hidden = true;
			overlay.replaceChildren();
			overlay.removeAttribute('class');
		}

		return;
	}

	const metaOpen = /** @type {HTMLElement | null} */ (target.closest('[data-meta-open]'));

	if (metaOpen) {
		const dialog = metaOpen
			.closest('[data-meta-owner]')
			?.querySelector(':scope > dialog[data-meta]');

		if (dialog instanceof HTMLDialogElement) {
			openDialog(dialog, {
				opener: metaOpen.checkVisibility({ visibilityProperty: true }) ? metaOpen : undefined,
				owner: metaOpen,
			});
		}

		return;
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', onClick);

	return () => {
		document.removeEventListener('click', onClick);
	};
}
