/** @import { FallbackLocale } from '../lib/fallback.js' */

import { openDialog } from '../lib/dialogs.js';
import { localeTitle, resolveFallback } from '../lib/fallback.js';

const SECTION = '[data-paths]';
const INPUT = 'input[data-path-locale]';
const PREVIEW = 'generated-paths';

/**
 * @param {string | undefined} json
 * @returns {unknown}
 */
function parse(json) {
	try {
		return JSON.parse(json ?? '');
	} catch {
		return null;
	}
}

/**
 * @param {HTMLElement} section
 * @returns {FallbackLocale[]}
 */
function locales(section) {
	const value = parse(section.dataset.locales);

	return Array.isArray(value)
		? value.filter(
				/** @returns {entry is FallbackLocale} */ (entry) =>
					typeof entry === 'object' &&
					entry !== null &&
					typeof (/** @type {FallbackLocale} */ (entry).id) === 'string' &&
					typeof (/** @type {FallbackLocale} */ (entry).title) === 'string',
			)
		: [];
}

/** @returns {Record<string, string>} */
function generated() {
	const value = parse(document.getElementById(PREVIEW)?.dataset.paths);
	/** @type {Record<string, string>} */
	const paths = {};

	if (typeof value === 'object' && value !== null) {
		for (const [locale, path] of Object.entries(value)) {
			if (typeof path === 'string') {
				paths[locale] = path;
			}
		}
	}

	return paths;
}

/**
 * @param {HTMLElement | undefined} row
 * @param {string} path
 * @param {string} note
 * @param {boolean} derived
 */
function render(row, path, note, derived) {
	const value = /** @type {HTMLElement | null} */ (row?.querySelector('[data-path-value]'));
	const hint = /** @type {HTMLElement | null} */ (row?.querySelector('[data-path-note]'));

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

/**
 * @param {HTMLInputElement} input
 * @param {string} suggestion
 * @param {string} path
 */
function offer(input, suggestion, path) {
	const offer = /** @type {HTMLElement | null} */ (
		input.closest('.field')?.querySelector('[data-path-suggestion]')
	);

	if (!offer) {
		return;
	}

	offer.hidden = suggestion === '' || suggestion === path;
	const value = offer.querySelector('[data-path-suggestion-value]');

	if (value) {
		value.textContent = suggestion;
	}
}

/**
 * @param {HTMLElement} section
 */
function refresh(section) {
	const configured = locales(section);
	const suggestions = generated();
	const inputs = Array.from(
		/** @type {NodeListOf<HTMLInputElement>} */ (section.querySelectorAll(INPUT)),
	);
	const rows = new Map(
		Array.from(
			/** @type {NodeListOf<HTMLElement>} */ (section.querySelectorAll('[data-path-row]')),
			(row) => [row.dataset.pathRow ?? '', row],
		),
	);
	/** @type {Record<string, string>} */
	const values = {};

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

function refreshAll() {
	/** @type {NodeListOf<HTMLElement>} */ (document.querySelectorAll(SECTION)).forEach(refresh);
}

/**
 * @param {Event} event
 */
function input(event) {
	const target = event.target;
	const section =
		target instanceof Element && target.matches(INPUT)
			? /** @type {HTMLElement | null} */ (target.closest(SECTION))
			: null;

	if (section) {
		refresh(section);
	}
}

/**
 * @param {MouseEvent} event
 */
function click(event) {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const open = /** @type {HTMLElement | null} */ (target.closest('[data-paths-open]'));

	if (open) {
		const dialog = open.closest(SECTION)?.querySelector(':scope > dialog[data-paths-dialog]');

		if (dialog instanceof HTMLDialogElement) {
			openDialog(dialog, { opener: open });
		}

		return;
	}

	const field = target.closest('[data-path-use]')?.closest('.field');
	const control = /** @type {HTMLInputElement | null} */ (field?.querySelector(INPUT));
	const suggestion = control ? generated()[control.dataset.pathLocale ?? ''] : undefined;

	if (control && suggestion) {
		control.value = suggestion;
		control.dispatchEvent(new Event('input', { bubbles: true }));
		control.focus();
	}
}

/** @returns {() => void} */
export function install() {
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
