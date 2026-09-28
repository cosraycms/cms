// Colour fields: the hex text input alone carries the value. The native
// picker behind the swatch writes into it; typing moves the swatch once
// the text is a colour, and leaving the input spells it the way the
// server stores it.

const HEX = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i;

/**
 * Lowercase `#rrggbb`, or null — the same rule as Value\Color::normalize().
 *
 * @param {string} text
 * @returns {string | null}
 */
export function normalize(text) {
	const hex = HEX.exec(text.trim())?.[1]?.toLowerCase();

	if (hex === undefined) return null;

	return `#${hex.length === 3 ? [...hex].map((digit) => digit + digit).join('') : hex}`;
}

/**
 * @param {Element} box
 * @returns {HTMLInputElement}
 */
function text(box) {
	return /** @type {HTMLInputElement} */ (box.querySelector('[data-color-value]'));
}

/**
 * @param {Element} box
 */
function render(box) {
	const picker = /** @type {HTMLInputElement} */ (box.querySelector('[data-color-picker]'));
	const swatch = /** @type {HTMLElement} */ (picker.parentElement);
	const color = normalize(text(box).value);

	swatch.toggleAttribute('data-empty', color === null);

	if (color === null) {
		swatch.style.removeProperty('--color');
	} else {
		swatch.style.setProperty('--color', color);
		picker.value = color;
	}
}

/**
 * @param {Event} event
 */
function onInput(event) {
	const control = event.target;
	if (!(control instanceof HTMLInputElement)) return;

	const box = control.closest('[data-color]');
	if (!box) return;

	if (control.matches('[data-color-picker]')) {
		// Re-announced on the named input, which dirty tracking, conditions
		// and error clearing watch.
		const value = text(box);
		value.value = control.value;
		value.dispatchEvent(new Event(event.type, { bubbles: true }));
	} else if (control.matches('[data-color-value]')) {
		const color = normalize(control.value);

		if (event.type === 'change' && color !== null) {
			control.value = color;
		}

		render(box);
	}
}

/**
 * @param {Event} event
 */
function stamp(event) {
	// A duplicate copies the named input into a fresh template; the swatch
	// still shows the template's value.
	if (event.target instanceof Element) {
		event.target.querySelectorAll('[data-color]').forEach(render);
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('input', onInput);
	document.addEventListener('change', onInput);
	document.addEventListener('repeater:stamp', stamp);

	return () => {
		document.removeEventListener('input', onInput);
		document.removeEventListener('change', onInput);
		document.removeEventListener('repeater:stamp', stamp);
	};
}
