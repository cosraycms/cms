import { openDialog } from '$lib/dialogs';
import { localeTitle, resolveFallback, type FallbackLocale } from '$lib/fallback';

const SECTION = '[data-paths]';
const INPUT = 'input[data-path-locale]';
const PREVIEW = 'generated-paths';

function parse(json: string | undefined): unknown {
	try {
		return JSON.parse(json ?? '');
	} catch {
		return null;
	}
}

function locales(section: HTMLElement): FallbackLocale[] {
	const value = parse(section.dataset.locales);

	return Array.isArray(value)
		? value.filter(
				(entry): entry is FallbackLocale =>
					typeof entry === 'object' &&
					entry !== null &&
					typeof (entry as FallbackLocale).id === 'string' &&
					typeof (entry as FallbackLocale).title === 'string',
			)
		: [];
}

function generated(): Record<string, string> {
	const value = parse(document.getElementById(PREVIEW)?.dataset.paths);
	const paths: Record<string, string> = {};

	if (typeof value === 'object' && value !== null) {
		for (const [locale, path] of Object.entries(value)) {
			if (typeof path === 'string') {
				paths[locale] = path;
			}
		}
	}

	return paths;
}

function render(row: HTMLElement | undefined, path: string, note: string, derived: boolean): void {
	const value = row?.querySelector<HTMLElement>('[data-path-value]');
	const hint = row?.querySelector<HTMLElement>('[data-path-note]');

	row?.classList.toggle('is-derived', derived);

	if (value) {
		value.textContent = path;
		value.hidden = path === '';
	}

	if (hint) {
		hint.textContent = note;
		hint.hidden = note === '';
	}
}

function offer(input: HTMLInputElement, suggestion: string, path: string): void {
	const offer = input.closest('.field')?.querySelector<HTMLElement>('[data-path-suggestion]');

	if (!offer) {
		return;
	}

	offer.hidden = suggestion === '' || suggestion === path;
	const value = offer.querySelector('[data-path-suggestion-value]');

	if (value) {
		value.textContent = suggestion;
	}
}

function refresh(section: HTMLElement): void {
	const configured = locales(section);
	const suggestions = generated();
	const inputs = Array.from(section.querySelectorAll<HTMLInputElement>(INPUT));
	const rows = new Map(
		Array.from(section.querySelectorAll<HTMLElement>('[data-path-row]'), (row) => [
			row.dataset.pathRow ?? '',
			row,
		]),
	);
	const values: Record<string, string> = {};

	for (const input of inputs) {
		values[input.dataset.pathLocale ?? ''] = input.value.trim();
	}

	// Saving generates paths only while every locale is empty; otherwise an
	// empty locale is served through its fallback chain (PathManager::path).
	const automatic = Object.values(values).every((value) => value === '');

	for (const input of inputs) {
		const locale = input.dataset.pathLocale ?? '';
		const value = values[locale] ?? '';
		const suggestion = suggestions[locale] ?? '';
		let path = value;
		let note = '';

		if (value === '' && automatic) {
			path = suggestion;
			note = (path === '' ? section.dataset.noteNone : section.dataset.noteGenerated) ?? '';
		} else if (value === '') {
			const fallback = resolveFallback(values, locale, configured);
			path = fallback?.value ?? '';
			note = fallback
				? (section.dataset.noteFallback ?? '').replace(
						'{language}',
						localeTitle(configured, fallback.locale),
					)
				: (section.dataset.noteNone ?? '');
		}

		input.placeholder = value === '' ? path : '';
		render(rows.get(locale), path, note, value === '');
		offer(input, suggestion, path);
	}
}

function refreshAll(): void {
	document.querySelectorAll<HTMLElement>(SECTION).forEach(refresh);
}

function input(event: Event): void {
	const target = event.target;
	const section =
		target instanceof Element && target.matches(INPUT)
			? target.closest<HTMLElement>(SECTION)
			: null;

	if (section) {
		refresh(section);
	}
}

function click(event: MouseEvent): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const open = target.closest<HTMLElement>('[data-paths-open]');

	if (open) {
		const dialog = open.closest(SECTION)?.querySelector(':scope > dialog[data-paths-dialog]');

		if (dialog instanceof HTMLDialogElement) {
			openDialog(dialog, { opener: open });
		}

		return;
	}

	const field = target.closest('[data-path-use]')?.closest('.field');
	const control = field?.querySelector<HTMLInputElement>(INPUT);
	const suggestion = control ? generated()[control.dataset.pathLocale ?? ''] : undefined;

	if (control && suggestion) {
		control.value = suggestion;
		control.dispatchEvent(new Event('input', { bubbles: true }));
		control.focus();
	}
}

export function install(): () => void {
	document.addEventListener('click', click);
	document.addEventListener('input', input);
	document.addEventListener('htmx:after:swap', refreshAll);
	refreshAll();

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('input', input);
		document.removeEventListener('htmx:after:swap', refreshAll);
	};
}
