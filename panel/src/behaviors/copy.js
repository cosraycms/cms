// A button marked data-copy="<id>" copies the text of the element with that
// id, a <template>'s content included, and confirms with a toast.

import { copyText } from '../lib/clipboard.js';
import { __ } from '../lib/locale.js';
import { toast } from '../lib/toast.js';

/**
 * @param {Element} source
 * @returns {string}
 */
function text(source) {
	return (source instanceof HTMLTemplateElement ? source.content : source).textContent ?? '';
}

/**
 * @param {MouseEvent} event
 */
function click(event) {
	const button = event.target instanceof Element ? event.target.closest('button[data-copy]') : null;
	const source =
		button instanceof HTMLElement ? document.getElementById(button.dataset.copy ?? '') : null;

	if (!source) {
		return;
	}

	void copyText(text(source)).then((copied) => {
		toast(copied ? 'success' : 'error', copied ? __('common:copied') : __('common:copy-failed'));
	});
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', click);

	return () => {
		document.removeEventListener('click', click);
	};
}
