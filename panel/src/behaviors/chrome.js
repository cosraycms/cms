// Editor chrome: the preview overlay's close button. The overlay anchor
// is only emptied, never removed — out-of-band swaps need the id to stay
// in the document.

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
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', onClick);

	return () => {
		document.removeEventListener('click', onClick);
	};
}
