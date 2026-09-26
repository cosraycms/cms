/** @import { FallbackLocale } from '../lib/fallback.js' */

import { localeTitle, resolveFallback } from '../lib/fallback.js';
import { ZXX } from '../lib/content.js';

const CONTENT_SCOPE = '[data-content-locale-scope]';
const CONTENT_CONTROL = '[data-content-locale-control]';
const INPUT = '[data-fallback-input]';
const BLOCK_VARIANT = ':scope > .field-body > .control > .variant[data-blocks-locale]';

/**
 * @param {Element} scope
 * @returns {FallbackLocale[]}
 */
function locales(scope) {
	try {
		/** @type {unknown} */
		const value = JSON.parse(scope.getAttribute('data-content-locales') ?? '[]');

		return Array.isArray(value)
			? value.filter(
					/** @returns {entry is FallbackLocale} */ (entry) =>
						typeof entry === 'object' &&
						entry !== null &&
						typeof (/** @type {FallbackLocale} */ (entry).id) === 'string' &&
						typeof (/** @type {FallbackLocale} */ (entry).title) === 'string',
				)
			: [];
	} catch {
		return [];
	}
}

/**
 * @param {Element} field
 * @returns {Array<HTMLInputElement | HTMLTextAreaElement>}
 */
function fieldInputs(field) {
	return Array.from(
		/** @type {NodeListOf<HTMLInputElement | HTMLTextAreaElement>} */ (
			field.querySelectorAll(`:scope > .field-body > .control > .variant[data-locale] ${INPUT}`)
		),
	);
}

/**
 * @param {Element} field
 */
function refreshField(field) {
	const scope = field.closest(CONTENT_SCOPE);
	const configured = scope ? locales(scope) : [];
	const controls = fieldInputs(field);
	/** @type {Record<string, string>} */
	const map = { [ZXX]: field.getAttribute('data-fallback-neutral') ?? '' };

	for (const control of controls) {
		const locale = /** @type {HTMLElement | null} */ (control.closest('.variant[data-locale]'))
			?.dataset.locale;

		if (locale) {
			map[locale] = control.value;
		}
	}

	for (const control of controls) {
		const variant = /** @type {HTMLElement | null} */ (control.closest('.variant[data-locale]'));
		const locale = variant?.dataset.locale ?? '';
		const editor =
			/** @type {HTMLInputElement | null} */ (
				control.closest('[data-youtube]')?.querySelector('[data-youtube-input]')
			) ?? control;
		const fallback =
			control.value === '' && document.activeElement !== editor
				? resolveFallback(map, locale, configured)
				: null;
		const schemaPlaceholder = control.dataset.schemaPlaceholder ?? '';
		const source = /** @type {HTMLElement | null} */ (
			variant?.querySelector(':scope > [data-fallback-source]')
		);

		editor.placeholder = fallback?.value ?? schemaPlaceholder;

		if (!source) {
			continue;
		}

		source.hidden = !fallback;
		source.textContent = fallback
			? (source.dataset.template ?? '{language}').replace(
					'{language}',
					fallback.locale === ZXX
						? (source.dataset.neutral ?? localeTitle(configured, fallback.locale))
						: localeTitle(configured, fallback.locale),
				)
			: '';
	}
}

/**
 * @param {Element} field
 */
function refreshBlockField(field) {
	const scope = /** @type {HTMLElement | null} */ (field.closest(CONTENT_SCOPE));

	// Single-language editors have no locale scope; keep their server-rendered variant.
	if (!scope) {
		return;
	}

	const active = scope.dataset.contentLocale ?? '';
	const configured = locales(scope);
	const variants = Array.from(
		/** @type {NodeListOf<HTMLElement>} */ (field.querySelectorAll(BLOCK_VARIANT)),
	);
	/** @type {Record<string, number>} */
	const counts = {};

	for (const variant of variants) {
		const locale = variant.dataset.locale ?? '';
		const list = variant.querySelector(':scope > .cms-blocks-editor > [data-repeater-list]');
		const source = /** @type {HTMLElement | null} */ (
			variant.querySelector(':scope > [data-blocks-fallback-source]')
		);

		counts[locale] = list?.children.length ?? 0;
		variant.classList.remove('is-fallback-preview');
		variant.inert = false;
		variant.hidden = locale !== active;
		if (source) source.hidden = true;
	}

	const activeVariant = variants.find((candidate) => candidate.dataset.locale === active);

	if ((counts[active] ?? 0) > 0 || activeVariant?.contains(document.activeElement)) {
		return;
	}

	const fallback = resolveFallback(counts, active, configured, (count) => (count ?? 0) > 0);
	const variant = variants.find((candidate) => candidate.dataset.locale === fallback?.locale);

	if (!fallback || !variant) {
		return;
	}

	variant.hidden = false;
	variant.inert = true;
	variant.classList.add('is-fallback-preview');

	for (const nested of /** @type {NodeListOf<HTMLElement>} */ (
		variant.querySelectorAll('.variant[data-locale]')
	)) {
		if (nested.closest('[data-blocks-locale]') === variant) {
			nested.hidden = nested.dataset.locale !== fallback.locale;
		}
	}

	for (const host of /** @type {NodeListOf<HTMLElement & { locale: string }>} */ (
		variant.querySelectorAll('cosray-host[data-translated]')
	)) {
		host.locale = fallback.locale;
	}

	const source = /** @type {HTMLElement | null} */ (
		variant.querySelector(':scope > [data-blocks-fallback-source]')
	);

	if (source) {
		source.hidden = false;
		source.textContent = (source.dataset.template ?? '{language}').replace(
			'{language}',
			localeTitle(configured, fallback.locale),
		);
	}
}

/**
 * @param {ParentNode} [root]
 */
function refresh(root = document) {
	root.querySelectorAll('.cms-field').forEach((field) => {
		if (fieldInputs(field).length > 0) {
			refreshField(field);
		}

		if (field.querySelector(BLOCK_VARIANT)) {
			refreshBlockField(field);
		}
	});
}

/**
 * @param {Event} event
 */
function input(event) {
	const target = event.target;

	if (target instanceof Element && target.matches(`${INPUT}, [data-fallback-editor]`)) {
		const field = target.closest('.cms-field');

		if (field) refreshField(field);
	}
}

/**
 * @param {Event} event
 */
function change(event) {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	if (target.matches(CONTENT_CONTROL)) {
		refresh();
		return;
	}

	const variant = target.closest('[data-blocks-locale]');
	const field = variant?.closest('.cms-field');

	if (field) refreshBlockField(field);
}

/**
 * @param {FocusEvent} event
 */
function focus(event) {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	if (target.matches(`${INPUT}, [data-fallback-editor]`)) {
		const field = target.closest('.cms-field');

		if (field) refreshField(field);
	}

	const blockField = target.closest('[data-blocks-locale]')?.closest('.cms-field');

	if (blockField) {
		queueMicrotask(() => refreshBlockField(blockField));
	}
}

/**
 * @param {Event} event
 */
function stamp(event) {
	if (event.target instanceof Element) {
		refresh(event.target);
		const field = event.target.closest('.cms-field');

		if (field?.querySelector(BLOCK_VARIANT)) refreshBlockField(field);
	}
}

function swapped() {
	refresh();
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('input', input);
	document.addEventListener('change', change);
	document.addEventListener('content-locale:change', change);
	document.addEventListener('focusin', focus);
	document.addEventListener('focusout', focus);
	document.addEventListener('repeater:stamp', stamp);
	document.addEventListener('htmx:after:swap', swapped);
	refresh();

	return () => {
		document.removeEventListener('input', input);
		document.removeEventListener('change', change);
		document.removeEventListener('content-locale:change', change);
		document.removeEventListener('focusin', focus);
		document.removeEventListener('focusout', focus);
		document.removeEventListener('repeater:stamp', stamp);
		document.removeEventListener('htmx:after:swap', swapped);
	};
}
