// Keeps the values a block's editor view follows current on its body: each
// sub-field named in data-block-values as data-value-{sub} and --value-{sub},
// rendered first by the row view. Editor views style the canvas from them.

const FIELD = /\[fields\]\[([^\]]+)\]\[value\]\[zxx\]$/;

/** @param {string} name */
function key(name) {
	return name.replace(/(?<!^)[A-Z]/g, (letter) => `-${letter}`).toLowerCase();
}

/**
 * @param {Element} control
 * @returns {string}
 */
function valueOf(control) {
	if (control instanceof HTMLInputElement && control.type === 'checkbox') {
		return control.checked ? 'true' : 'false';
	}

	return control instanceof HTMLInputElement ||
		control instanceof HTMLSelectElement ||
		control instanceof HTMLTextAreaElement
		? control.value
		: '';
}

/**
 * @param {HTMLElement} body
 * @param {string} sub
 * @param {string} value
 */
function reflect(body, sub, value) {
	body.setAttribute(`data-value-${key(sub)}`, value);

	if (value === '') {
		body.style.removeProperty(`--value-${key(sub)}`);
	} else {
		body.style.setProperty(`--value-${key(sub)}`, value);
	}
}

/**
 * @param {Element} control
 * @returns {HTMLElement | null}
 */
function bodyOf(control) {
	const body = control
		.closest('[data-repeater-row]')
		?.querySelector(':scope > [data-block-values]');

	return body instanceof HTMLElement ? body : null;
}

/** @param {HTMLElement} body */
function subsOf(body) {
	return (body.dataset.blockValues ?? '').split(' ').filter((sub) => sub !== '');
}

/** @param {Event} event */
function change(event) {
	const control = event.target;
	if (!(control instanceof Element)) return;

	const sub = control.getAttribute('name')?.match(FIELD)?.[1];
	const body = sub ? bodyOf(control) : null;
	if (!sub || !body || !subsOf(body).includes(sub)) return;
	if (control instanceof HTMLInputElement && control.type === 'radio' && !control.checked) return;

	reflect(body, sub, valueOf(control));
}

/** @param {HTMLElement} body */
function refresh(body) {
	const row = body.closest('[data-repeater-row]');
	if (!row) return;

	for (const sub of subsOf(body)) {
		const controls = [...row.querySelectorAll('[name]')].filter(
			(control) => control.getAttribute('name')?.match(FIELD)?.[1] === sub,
		);
		// A checkbox posts after its empty presence marker, a radio group its checked choice.
		const control =
			controls.find((entry) => entry instanceof HTMLInputElement && entry.type === 'checkbox') ??
			controls.find(
				(entry) => entry instanceof HTMLInputElement && entry.type === 'radio' && entry.checked,
			) ??
			controls.find((entry) => !(entry instanceof HTMLInputElement && entry.type === 'radio'));

		if (control) reflect(body, sub, valueOf(control));
	}
}

/** @param {Event} event */
function stamp(event) {
	if (event.target instanceof Element) {
		event.target.querySelectorAll('[data-block-values]').forEach((body) => {
			if (body instanceof HTMLElement) refresh(body);
		});
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('input', change);
	document.addEventListener('change', change);
	document.addEventListener('repeater:stamp', stamp);

	return () => {
		document.removeEventListener('input', change);
		document.removeEventListener('change', change);
		document.removeEventListener('repeater:stamp', stamp);
	};
}
