const ID = /^[A-Za-z0-9_-]{11}$/;

/**
 * @param {string} text
 * @returns {string | null}
 */
export function urlId(text) {
	try {
		const url = new URL(/^https?:\/\//i.test(text.trim()) ? text.trim() : `https://${text.trim()}`);
		const host = url.hostname;
		/** @type {string | null} */
		let id = null;

		if (host === 'youtu.be') {
			id = /^\/([^/]+)\/?$/.exec(url.pathname)?.[1] ?? null;
		} else if (
			[
				'youtube.com',
				'www.youtube.com',
				'm.youtube.com',
				'music.youtube.com',
				'youtube-nocookie.com',
				'www.youtube-nocookie.com',
			].includes(host)
		) {
			id =
				url.pathname === '/watch'
					? url.searchParams.get('v')
					: (/^\/(?:shorts|embed|live|v)\/([^/]+)\/?$/.exec(url.pathname)?.[1] ?? null);
		}

		return id !== null && ID.test(id) ? id : null;
	} catch {
		return null;
	}
}

/**
 * @param {string} id
 * @returns {string}
 */
export function thumbnail(id) {
	return `https://i.ytimg.com/vi/${id}/hqdefault.jpg`;
}

/**
 * @param {Element} box
 * @returns {HTMLInputElement}
 */
function value(box) {
	return /** @type {HTMLInputElement} */ (
		/** @type {HTMLInputElement | null} */ (box.querySelector('[data-youtube-value]'))
	);
}

/**
 * @param {Element} box
 * @returns {HTMLInputElement}
 */
function input(box) {
	return /** @type {HTMLInputElement} */ (
		/** @type {HTMLInputElement | null} */ (box.querySelector('[data-youtube-input]'))
	);
}

/**
 * @param {Element} box
 * @param {string} selector
 * @param {boolean} visible
 */
function show(box, selector, visible) {
	const element = /** @type {HTMLElement | null} */ (box.querySelector(selector));
	if (element) element.hidden = !visible;
}

/**
 * @param {Element} box
 */
function clearError(box) {
	const editor = input(box);
	const error = /** @type {HTMLElement} */ (
		/** @type {HTMLElement | null} */ (box.querySelector('[data-youtube-error]'))
	);

	if (editor.getAttribute('aria-describedby') === error.id) {
		editor.removeAttribute('aria-invalid');
		editor.removeAttribute('aria-describedby');
	}
	error.hidden = true;
}

/**
 * @param {Element} box
 */
function actions(box) {
	show(box, '[data-youtube-add]', input(box).value.trim() !== '');
}

/**
 * @param {Element} box
 */
function render(box) {
	const id = value(box).value;
	const filled = ID.test(id);
	const player = /** @type {HTMLIFrameElement} */ (
		/** @type {HTMLIFrameElement | null} */ (box.querySelector('[data-youtube-player]'))
	);

	if (filled) {
		const src = `https://www.youtube-nocookie.com/embed/${id}`;
		if (player.getAttribute('src') !== src) player.src = src;
	} else {
		player.removeAttribute('src');
	}

	input(box).value = id;
	clearError(box);
	show(box, '[data-youtube-player]', filled);
	show(box, '[data-youtube-entry]', !filled);
	show(box, '[data-youtube-replace]', filled);
	actions(box);
}

/**
 * @param {Element} box
 * @param {string} id
 */
function commit(box, id) {
	const control = value(box);
	const changed = control.value !== id;
	control.value = id;
	render(box);
	/** @type {HTMLElement | null} */ (
		box.querySelector(id === '' ? '[data-youtube-input]' : '[data-youtube-replace]')
	)?.focus();

	if (changed) {
		control.dispatchEvent(new Event('input', { bubbles: true }));
		control.dispatchEvent(new Event('change', { bubbles: true }));
	}
}

/**
 * @param {Element} box
 */
function confirm(box) {
	const text = input(box).value.trim();
	const id = ID.test(text) ? text : urlId(text);

	if (id === null) {
		const error = /** @type {HTMLElement} */ (
			/** @type {HTMLElement | null} */ (box.querySelector('[data-youtube-error]'))
		);
		error.hidden = false;
		input(box).setAttribute('aria-invalid', 'true');
		input(box).setAttribute('aria-describedby', error.id);
		input(box).focus();
		return;
	}

	commit(box, id);
}

const RATIO_META = /^(.*)\[meta\]\[aspectRatio[XY]\]\[zxx\]$/;

/**
 * @param {string} root
 * @param {'X' | 'Y'} axis
 * @returns {number | null}
 */
function side(root, axis) {
	const control = document.getElementsByName(`${root}[meta][aspectRatio${axis}][zxx]`)[0];
	const number = control instanceof HTMLInputElement ? Number(control.value) : NaN;

	return Number.isInteger(number) && number > 0 ? number : null;
}

/**
 * @param {HTMLInputElement} control
 */
function followRatio(control) {
	const root = RATIO_META.exec(control.name)?.[1];
	if (root === undefined) return;

	const x = side(root, 'X');
	const y = side(root, 'Y');
	if (x === null || y === null) return;

	for (const box of /** @type {NodeListOf<HTMLElement>} */ (
		document.querySelectorAll('[data-youtube]')
	)) {
		if (value(box).name.startsWith(`${root}[value]`)) {
			box.style.setProperty('--ratio', `${x} / ${y}`);
		}
	}
}

/**
 * @param {Event} event
 */
function onInput(event) {
	const control = event.target;
	if (!(control instanceof HTMLInputElement)) return;

	followRatio(control);
	const box = control.closest('[data-youtube]');
	if (!box) return;

	if (control.matches('[data-youtube-input]')) {
		clearError(box);
		actions(box);
	} else if (control.matches('[data-youtube-value]')) {
		render(box);
	}
}

/**
 * @param {MouseEvent} event
 */
function onClick(event) {
	const button = event.target instanceof Element ? event.target.closest('button') : null;
	const box = button?.closest('[data-youtube]');
	if (!button || !box || input(box).readOnly) return;

	if (button.hasAttribute('data-youtube-replace')) commit(box, '');
	else if (button.hasAttribute('data-youtube-add')) confirm(box);
}

/**
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
	const control = event.target;
	if (!(control instanceof HTMLInputElement) || !control.matches('[data-youtube-input]')) return;
	if (event.isComposing || control.readOnly || event.key !== 'Enter') return;

	event.preventDefault();
	confirm(/** @type {Element} */ (control.closest('[data-youtube]')));
}

/**
 * @param {Event} event
 */
function stamp(event) {
	if (event.target instanceof Element) {
		event.target.querySelectorAll('[data-youtube]').forEach(render);
		// Duplicated rows copy live meta inputs, not the template's preview ratio.
		/** @type {NodeListOf<HTMLInputElement>} */ (
			event.target.querySelectorAll('input[type="number"]')
		).forEach(followRatio);
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('input', onInput);
	document.addEventListener('change', onInput);
	document.addEventListener('click', onClick);
	document.addEventListener('keydown', onKeydown);
	document.addEventListener('repeater:stamp', stamp);

	return () => {
		document.removeEventListener('input', onInput);
		document.removeEventListener('change', onInput);
		document.removeEventListener('click', onClick);
		document.removeEventListener('keydown', onKeydown);
		document.removeEventListener('repeater:stamp', stamp);
	};
}
