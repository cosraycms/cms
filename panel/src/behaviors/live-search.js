// A search form marked data-live-search submits a moment after typing
// pauses, so results follow the query without pressing Enter. The submit
// re-renders the page, the field included; the field then gets back its
// focus, its caret and whatever was typed while the request ran. Without
// scripts, Enter still submits.

const FORM = 'form[data-live-search]';
const FIELD = 'input[type="search"]';
const DELAY = 300;

/** @type {ReturnType<typeof setTimeout> | undefined} */
let timer;

/** @type {{ name: string, value: string, start: number | null, end: number | null } | null} */
let typing = null;

/**
 * @param {Event} event
 */
function input(event) {
	const field = event.target;

	if (
		!(field instanceof HTMLInputElement) ||
		!field.matches(FIELD) ||
		!field.form?.matches(FORM) ||
		(event instanceof InputEvent && event.isComposing)
	) {
		return;
	}

	clearTimeout(timer);
	timer = setTimeout(() => submit(field.name), DELAY);
	typing = {
		name: field.name,
		value: field.value,
		start: field.selectionStart,
		end: field.selectionEnd,
	};
}

/**
 * The form is looked up when the delay ends, since a swap in between
 * replaced the one the typing happened in.
 *
 * @param {string} name
 */
function submit(name) {
	const field = document.querySelector(`${FORM} ${FIELD}[name="${name}"]`);

	if (field instanceof HTMLInputElement && field.form) {
		field.form.requestSubmit();
	}
}

function restore() {
	if (!typing) {
		return;
	}

	const field = document.querySelector(`${FORM} ${FIELD}[name="${typing.name}"]`);

	if (!(field instanceof HTMLInputElement)) {
		typing = null;

		return;
	}

	field.value = typing.value;
	field.focus({ preventScroll: true });
	field.setSelectionRange(typing.start, typing.end);

	// Done once the response matches the field; a pending delay keeps it.
	if (timer === undefined) {
		typing = null;
	}
}

/**
 * @param {Event} event
 */
function sent(event) {
	if (event.target instanceof Element && event.target.matches(FORM)) {
		clearTimeout(timer);
		timer = undefined;
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('input', input);
	document.addEventListener('submit', sent);
	document.addEventListener('htmx:finally:swap', restore);

	return () => {
		clearTimeout(timer);
		timer = undefined;
		typing = null;
		document.removeEventListener('input', input);
		document.removeEventListener('submit', sent);
		document.removeEventListener('htmx:finally:swap', restore);
	};
}
