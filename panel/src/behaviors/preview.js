// The editor's preview: the server swaps a filled dialog into the anchor
// out of band, and it opens here. The anchor stays in the document when
// closed — out-of-band swaps need its id.

import { openDialog } from '../lib/dialogs.js';

function open() {
	const dialog = document.getElementById('editor-preview');

	if (!(dialog instanceof HTMLDialogElement) || dialog.open || !dialog.querySelector('iframe')) {
		return;
	}

	openDialog(dialog, {
		// Unloads the previewed page.
		onClose: () => dialog.replaceChildren(),
	});
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('htmx:after:swap', open);

	return () => {
		document.removeEventListener('htmx:after:swap', open);
	};
}
