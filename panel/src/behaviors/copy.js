// A button marked data-copy="<id>" copies the text of the element with that
// id, a <template>'s content included, and confirms with a toast. Without a
// secure context, as on a plain http development host, there is no clipboard
// API, so a selected scratch field and the copy command stand in.

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
 * @param {string} value
 * @returns {boolean}
 */
function fallback(value) {
	const field = document.createElement('textarea');
	field.value = value;
	field.readOnly = true;
	field.style.position = 'fixed';
	field.style.opacity = '0';
	document.body.append(field);
	field.select();

	try {
		return document.execCommand('copy');
	} catch {
		return false;
	} finally {
		field.remove();
	}
}

/**
 * @param {string} value
 * @returns {Promise<boolean>}
 */
async function write(value) {
	try {
		await navigator.clipboard.writeText(value);

		return true;
	} catch {
		return fallback(value);
	}
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

	void write(text(source)).then((copied) => {
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
