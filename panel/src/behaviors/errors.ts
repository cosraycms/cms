// Field-level validation errors for the SSR editor form.
//
// A failed save swaps the #editor-errors summary out-of-band; each issue
// carries the sire data path of the failing value in data-error-path.
// Form control names mirror that data structure (content[f][value][de]),
// so a path resolves to its control by building the name and falling
// back to ever shorter prefixes — the fallback is what finds an element
// control's host, whose single [json] leaf has no per-locale input.
//
// Marks are re-derived from the summary whenever a NEW summary arrives
// (tracked by element identity — other swaps, like the route-path
// preview, must not repaint or steal focus). Editing a field clears its
// marks; the summary itself stays until the server speaks again.

const BOX = 'editor-errors';
const FORM = 'node-editor-form';
const INVALID = 'data-invalid';
const MESSAGE = 'data-error-message';

type Path = Array<string | number>;

let lastBox: Element | null = null;
let counter = 0;

function nameFor(path: Path): string {
	return (
		String(path[0]) +
		path
			.slice(1)
			.map((segment) => `[${String(segment)}]`)
			.join('')
	);
}

function parsePath(item: Element): Path | null {
	try {
		const parsed: unknown = JSON.parse(item.getAttribute('data-error-path') ?? '');

		if (
			Array.isArray(parsed) &&
			parsed.length > 0 &&
			parsed.every((segment) => typeof segment === 'string' || typeof segment === 'number')
		) {
			return parsed as Path;
		}
	} catch {
		// Fall through to null: a malformed path renders in the summary
		// but cannot be targeted.
	}

	return null;
}

// Escapes a form name for use inside a quoted attribute selector; the
// brackets need it too — not per CSS grammar, but jsdom's selector
// engine rejects them unescaped.
function selectorValue(name: string): string {
	return name.replace(/[\\"[\]]/g, '\\$&');
}

function resolve(form: Element, path: Path): Element | null {
	for (let end = path.length; end > 0; end--) {
		const name = nameFor(path.slice(0, end));
		const exact = form.querySelector(`[name="${selectorValue(name)}"]`);

		if (exact) {
			return exact;
		}

		// The prefix probe appends "[" so content[f] cannot match a
		// sibling field content[ff]. It never runs on a single segment:
		// "content[" would claim the first content field for any path.
		if (end > 1) {
			const prefixed = form.querySelector(`[name^="${selectorValue(`${name}[`)}"]`);

			if (prefixed) {
				return prefixed;
			}
		}
	}

	return null;
}

function wrapper(control: Element): Element {
	return control.closest('.cms-field') ?? control.parentElement ?? control;
}

function unmark(field: Element): void {
	field.querySelectorAll(`[${MESSAGE}]`).forEach((message) => message.remove());
	field.querySelectorAll('[aria-invalid]').forEach((control) => {
		control.removeAttribute('aria-invalid');
		control.removeAttribute('aria-describedby');
	});
	field.querySelectorAll('.has-error').forEach((badge) => badge.classList.remove('has-error'));
	field.removeAttribute(INVALID);
}

function wipe(): void {
	document.querySelectorAll(`[${INVALID}]`).forEach(unmark);
	document.querySelectorAll(`[${MESSAGE}]`).forEach((message) => message.remove());
}

function mark(control: Element, message: string): void {
	const field = wrapper(control);
	field.setAttribute(INVALID, 'true');

	const note = document.createElement('p');
	note.className = 'cms-field-error';
	note.setAttribute(MESSAGE, '');
	note.id = `cms-error-${++counter}`;
	note.textContent = message;

	const messages = field.querySelectorAll(`:scope > [${MESSAGE}]`);
	const anchor = messages[messages.length - 1] ?? field.querySelector(':scope > .control');

	if (anchor) {
		anchor.after(note);
	} else {
		field.append(note);
	}

	if (
		control instanceof HTMLInputElement ||
		control instanceof HTMLTextAreaElement ||
		control instanceof HTMLSelectElement
	) {
		control.setAttribute('aria-invalid', 'true');
		control.setAttribute('aria-describedby', note.id);
	}

	// The errored locale's variant may be hidden behind a tab; badge it.
	const variant = control.closest('.variant[data-locale]');

	if (variant instanceof HTMLElement && variant.hidden) {
		field
			.querySelector(`[data-locale-tab="${variant.dataset.locale}"]`)
			?.classList.add('has-error');
	}

	// An issue inside the meta dialog is invisible until opened.
	if (control.closest('dialog[data-meta]')) {
		field.querySelector('[data-meta-open]')?.classList.add('has-error');
	}
}

function paint(box: Element | null): void {
	wipe();

	const form = document.getElementById(FORM);

	if (!(box instanceof HTMLElement) || box.hidden || !form) {
		return;
	}

	box.querySelectorAll('[data-error-path]').forEach((item) => {
		const path = parsePath(item);
		const control = path && resolve(form, path);

		if (control) {
			mark(control, item.textContent?.trim() ?? '');
		}
	});

	// The error-summary pattern: announce and focus the box on arrival.
	box.focus();
}

function swapped(): void {
	const box = document.getElementById(BOX);

	if (box === lastBox) {
		return;
	}

	lastBox = box;
	paint(box);
}

// Reveal the control (locale tab, collapsed rows, meta dialog), then go there.
function activate(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const item = target.closest('[data-error-path]');
	const form = document.getElementById(FORM);

	if (!item || !form) {
		return;
	}

	const path = parsePath(item);
	const control = path && resolve(form, path);

	if (!control) {
		return;
	}

	const field = wrapper(control);
	const variant = control.closest('.variant[data-locale]');

	if (variant instanceof HTMLElement && variant.hidden) {
		const tab = field.querySelector(`[data-locale-tab="${variant.dataset.locale}"]`);

		if (tab instanceof HTMLElement) {
			tab.click();
		}
	}

	for (
		let body = control.closest('[data-repeater-body]');
		body;
		body = body.parentElement?.closest('[data-repeater-body]') ?? null
	) {
		if (body instanceof HTMLElement && body.hidden) {
			const toggle = body.closest('[data-repeater-row]')?.querySelector('[data-repeater-collapse]');

			if (toggle instanceof HTMLElement) {
				toggle.click();
			}
		}
	}

	const dialog = control.closest('dialog[data-meta]');

	if (dialog instanceof HTMLDialogElement && !dialog.open) {
		field.querySelector<HTMLElement>('[data-meta-open]')?.click();
	}

	if (field instanceof HTMLElement) {
		field.scrollIntoView?.({ block: 'center' });
	}

	if (control instanceof HTMLElement) {
		control.focus?.();
	}
}

function clear(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const field = target.closest(`[${INVALID}]`);

	if (field) {
		unmark(field);
	}
}

export function install(): () => void {
	document.addEventListener('htmx:after:swap', swapped);
	document.addEventListener('click', activate);
	document.addEventListener('input', clear);
	document.addEventListener('change', clear);
	document.addEventListener('cosray-change', clear);

	return () => {
		document.removeEventListener('htmx:after:swap', swapped);
		document.removeEventListener('click', activate);
		document.removeEventListener('input', clear);
		document.removeEventListener('change', clear);
		document.removeEventListener('cosray-change', clear);
		lastBox = null;
	};
}
