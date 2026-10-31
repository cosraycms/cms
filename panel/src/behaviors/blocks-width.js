import { remember } from '../lib/panel-cookie.js';

// Grid blocks fields that fill a row can spread past the editor's column to
// the pane's width. One choice covers every such field; it lives in the panel
// cookie the server reads, so a page arrives in its state and never jumps.

const TOGGLE = '[data-blocks-width]';
const EDITOR = '.cms-node';
const WIDE = 'data-wide-blocks';

function sync() {
	document.querySelectorAll(TOGGLE).forEach((toggle) => {
		const wide = toggle.closest(EDITOR)?.hasAttribute(WIDE) ?? false;
		toggle.setAttribute('aria-pressed', String(wide));
	});
}

/**
 * @param {Event} event
 */
function click(event) {
	const target = event.target;
	const toggle = target instanceof Element ? target.closest(TOGGLE) : null;
	const editor = toggle?.closest(EDITOR);

	if (!editor) {
		return;
	}

	const wide = !editor.hasAttribute(WIDE);
	editor.toggleAttribute(WIDE, wide);
	remember('blocks', wide ? 'wide' : null);
	sync();
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', click);
	document.addEventListener('htmx:after:swap', sync);
	sync();

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('htmx:after:swap', sync);
	};
}
