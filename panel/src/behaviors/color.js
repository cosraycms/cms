// Colour fields: the hex text input alone carries the value. The native
// picker behind the swatch and the palette buttons write into it; typing
// moves the swatch once the text is a colour, and leaving the input
// spells it the way the server stores it.

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

	box.querySelectorAll('[data-color-option]').forEach((option) => {
		option.setAttribute('aria-pressed', String(option.getAttribute('data-color-option') === color));
	});
}

/**
 * Writes a colour into the named input and announces it there, which
 * dirty tracking, conditions and error clearing watch.
 *
 * @param {Element} box
 * @param {string} color
 * @param {string[]} types
 */
function write(box, color, types) {
	const value = text(box);
	value.value = color;
	types.forEach((type) => value.dispatchEvent(new Event(type, { bubbles: true })));
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
		write(box, control.value, [event.type]);
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
function onClick(event) {
	const option =
		event.target instanceof Element ? event.target.closest('[data-color-option]') : null;
	const box = option?.closest('[data-color]');
	if (!option || !box || /** @type {HTMLButtonElement} */ (option).disabled) return;

	write(box, option.getAttribute('data-color-option') ?? '', ['input', 'change']);
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
	document.addEventListener('click', onClick);
	document.addEventListener('repeater:stamp', stamp);

	return () => {
		document.removeEventListener('input', onInput);
		document.removeEventListener('change', onInput);
		document.removeEventListener('click', onClick);
		document.removeEventListener('repeater:stamp', stamp);
	};
}
