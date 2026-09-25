import { selectContentLocale } from './content-locales';
import { revealInspector } from './inspector';
import { revealTab } from './tabs';

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
			return exact.closest('[data-youtube]')?.querySelector('[data-youtube-input]') ?? exact;
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

/** A control inside a meta dialog belongs to the dialog's owner — the field
 * wrapper, or the block row whose settings dialog took the group. */
function wrapper(control: Element): Element {
	const owner = control.closest('dialog[data-meta]')?.closest('[data-meta-owner]');

	return owner ?? control.closest('.cms-field') ?? control.parentElement ?? control;
}

function contentLocaleIds(control: HTMLElement): Set<string> {
	if (control instanceof HTMLSelectElement) {
		return new Set(Array.from(control.options, (option) => option.value));
	}

	return new Set(
		Array.from(
			control.querySelectorAll<HTMLElement>('[data-content-locale-option]'),
			(option) => option.dataset.contentLocaleOption ?? '',
		).filter((locale) => locale !== ''),
	);
}

function contentLocale(form: Element, path: Path, control: Element): string | undefined {
	const localeControl = form.querySelector<HTMLElement>('[data-content-locale-control]');

	if (!localeControl || path[0] !== 'content') {
		return undefined;
	}

	const ids = contentLocaleIds(localeControl);
	const variant = control.closest<HTMLElement>('.variant[data-locale]');

	if (variant?.dataset.locale && ids.has(variant.dataset.locale)) {
		return variant.dataset.locale;
	}

	return path
		.slice(2)
		.find((segment): segment is string => typeof segment === 'string' && ids.has(segment));
}

function refreshContentBadge(form: Element): void {
	const locales = new Set<string>();

	form.querySelectorAll(`[${INVALID}][data-error-locales]`).forEach((field) => {
		for (const locale of field.getAttribute('data-error-locales')?.split(' ') ?? []) {
			if (locale !== '') locales.add(locale);
		}
	});

	form.querySelectorAll<HTMLElement>('[data-content-locale-control]').forEach((control) => {
		control.classList.toggle('has-error', locales.size > 0);
		control.toggleAttribute('aria-invalid', locales.size > 0);
		control.querySelectorAll<HTMLElement>('[data-content-locale-option]').forEach((option) => {
			option.classList.toggle('has-error', locales.has(option.dataset.contentLocaleOption ?? ''));
		});

		if (locales.size > 0) {
			control.dataset.errorLocales = [...locales].join(' ');
		} else {
			delete control.dataset.errorLocales;
		}
	});
}

// A tab whose panel holds an issue shows it, since the panel may be hidden,
// and so does its shortcut in the collapsed inspector.
function refreshTabBadges(form: Element): void {
	form.querySelectorAll<HTMLElement>('[data-tabs] [role="tab"][aria-controls]').forEach((tab) => {
		const panel = document.getElementById(tab.getAttribute('aria-controls') ?? '');
		const invalid = panel?.querySelector(`[${INVALID}]`) != null;

		tab.classList.toggle('has-error', invalid);
		form.querySelector(`[data-inspector-open="${tab.id}"]`)?.classList.toggle('has-error', invalid);
	});
}

function addErrorLocale(field: Element, locale: string | undefined): void {
	if (!locale) {
		return;
	}

	const locales = new Set(field.getAttribute('data-error-locales')?.split(' ') ?? []);
	locales.delete('');
	locales.add(locale);
	field.setAttribute('data-error-locales', [...locales].join(' '));
}

function unmark(field: Element): void {
	field.querySelectorAll(`[${MESSAGE}]`).forEach((message) => message.remove());
	field.querySelectorAll('[aria-invalid]').forEach((control) => {
		control.removeAttribute('aria-invalid');
		control.removeAttribute('aria-describedby');
	});
	field.querySelectorAll('.has-error').forEach((badge) => badge.classList.remove('has-error'));
	field.removeAttribute(INVALID);
	field.removeAttribute('data-error-locales');

	const form = field.closest('form');

	if (form) {
		refreshContentBadge(form);
		refreshTabBadges(form);
	}
}

function wipe(): void {
	document.querySelectorAll(`[${INVALID}]`).forEach(unmark);
	document.querySelectorAll(`[${MESSAGE}]`).forEach((message) => message.remove());
	document.querySelectorAll('[data-content-locale-control]').forEach((control) => {
		control.classList.remove('has-error');
		control.removeAttribute('aria-invalid');
		control
			.querySelectorAll('[data-content-locale-option].has-error')
			.forEach((option) => option.classList.remove('has-error'));
		delete (control as HTMLElement).dataset.errorLocales;
	});
	document
		.querySelectorAll('[data-tabs] [role="tab"].has-error')
		.forEach((tab) => tab.classList.remove('has-error'));
}

function mark(control: Element, message: string, locale?: string): void {
	const field = wrapper(control);
	field.setAttribute(INVALID, 'true');
	addErrorLocale(field, locale);

	const note = document.createElement('p');
	note.className = 'cms-field-error';
	note.setAttribute(MESSAGE, '');
	note.id = `cms-error-${++counter}`;
	note.textContent = message;

	const body = field.querySelector(':scope > .field-body') ?? field;
	const messages = body.querySelectorAll(`:scope > [${MESSAGE}]`);
	const anchor =
		messages[messages.length - 1] ??
		body.querySelector(':scope > .description') ??
		body.querySelector(':scope > .control');

	if (anchor) {
		anchor.after(note);
	} else {
		body.append(note);
	}

	if (
		control instanceof HTMLInputElement ||
		control instanceof HTMLTextAreaElement ||
		control instanceof HTMLSelectElement
	) {
		control.setAttribute('aria-invalid', 'true');
		control.setAttribute('aria-describedby', note.id);
	}

	// An issue inside the meta dialog is invisible until opened.
	if (control.closest('dialog[data-meta]')) {
		field.querySelector('[data-meta-open]')?.classList.add('has-error');
	}

	const form = field.closest('form');

	if (form) {
		refreshContentBadge(form);
		refreshTabBadges(form);
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

		if (control && path) {
			mark(control, item.textContent?.trim() ?? '', contentLocale(form, path, control));
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

// Reveal the control (content language, inspector and its tab, collapsed
// rows, meta or paths dialog), then go there. The inspector opens before a
// dialog inside it: a dialog in a hidden drawer would open invisible.
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
	const globalScope =
		form.closest('[data-content-locale-scope]') ??
		form.querySelector('[data-content-locale-scope]');
	const locale = path ? contentLocale(form, path, control) : undefined;

	if (globalScope && locale) {
		selectContentLocale(globalScope, locale);
	}

	revealTab(control);
	revealInspector(control);

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

	const paths = control.closest('dialog[data-paths-dialog]');

	if (paths instanceof HTMLDialogElement && !paths.open) {
		paths.closest('[data-paths]')?.querySelector<HTMLElement>('[data-paths-open]')?.click();
	}

	if (control.closest('[data-youtube-entry]')?.hasAttribute('hidden')) {
		control
			.closest('[data-youtube]')
			?.querySelector<HTMLButtonElement>('[data-youtube-replace]')
			?.click();
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
