import { localeTitle, resolveFallback, type FallbackLocale } from '$lib/fallback';
import { ZXX } from '$types/data';

const CONTENT_SCOPE = '[data-content-locale-scope]';
const CONTENT_CONTROL = '[data-content-locale-control]';
const INPUT = '[data-fallback-input]';
const BLOCK_VARIANT = ':scope > .field-body > .control > .variant[data-blocks-locale]';

function locales(scope: Element): FallbackLocale[] {
	try {
		const value: unknown = JSON.parse(scope.getAttribute('data-content-locales') ?? '[]');

		return Array.isArray(value)
			? value.filter(
					(entry): entry is FallbackLocale =>
						typeof entry === 'object' &&
						entry !== null &&
						typeof (entry as FallbackLocale).id === 'string' &&
						typeof (entry as FallbackLocale).title === 'string',
				)
			: [];
	} catch {
		return [];
	}
}

function fieldInputs(field: Element): Array<HTMLInputElement | HTMLTextAreaElement> {
	return Array.from(
		field.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>(
			`:scope > .field-body > .control > .variant[data-locale] ${INPUT}`,
		),
	);
}

function refreshField(field: Element): void {
	const scope = field.closest(CONTENT_SCOPE);
	const configured = scope ? locales(scope) : [];
	const controls = fieldInputs(field);
	const map: Record<string, string> = { [ZXX]: field.getAttribute('data-fallback-neutral') ?? '' };

	for (const control of controls) {
		const locale = control.closest<HTMLElement>('.variant[data-locale]')?.dataset.locale;

		if (locale) {
			map[locale] = control.value;
		}
	}

	for (const control of controls) {
		const variant = control.closest<HTMLElement>('.variant[data-locale]');
		const locale = variant?.dataset.locale ?? '';
		const fallback =
			control.value === '' && document.activeElement !== control
				? resolveFallback(map, locale, configured)
				: null;
		const schemaPlaceholder = control.dataset.schemaPlaceholder ?? '';
		const source = variant?.querySelector<HTMLElement>(':scope > [data-fallback-source]');

		control.placeholder = fallback?.value ?? schemaPlaceholder;

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

function refreshBlockField(field: Element): void {
	const scope = field.closest<HTMLElement>(CONTENT_SCOPE);
	const active = scope?.dataset.contentLocale ?? '';
	const configured = scope ? locales(scope) : [];
	const variants = Array.from(field.querySelectorAll<HTMLElement>(BLOCK_VARIANT));
	const counts: Record<string, number> = {};

	for (const variant of variants) {
		const locale = variant.dataset.locale ?? '';
		const list = variant.querySelector(':scope > .cms-blocks-editor > [data-repeater-list]');
		const source = variant.querySelector<HTMLElement>(':scope > [data-blocks-fallback-source]');

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

	for (const nested of variant.querySelectorAll<HTMLElement>('.variant[data-locale]')) {
		if (nested.closest('[data-blocks-locale]') === variant) {
			nested.hidden = nested.dataset.locale !== fallback.locale;
		}
	}

	for (const host of variant.querySelectorAll<HTMLElement & { locale: string }>(
		'cosray-host[data-translated]',
	)) {
		host.locale = fallback.locale;
	}

	const source = variant.querySelector<HTMLElement>(':scope > [data-blocks-fallback-source]');

	if (source) {
		source.hidden = false;
		source.textContent = (source.dataset.template ?? '{language}').replace(
			'{language}',
			localeTitle(configured, fallback.locale),
		);
	}
}

function refresh(root: ParentNode = document): void {
	root.querySelectorAll('.cms-field').forEach((field) => {
		if (fieldInputs(field).length > 0) {
			refreshField(field);
		}

		if (field.querySelector(BLOCK_VARIANT)) {
			refreshBlockField(field);
		}
	});
}

function input(event: Event): void {
	const target = event.target;

	if (target instanceof Element && target.matches(INPUT)) {
		const field = target.closest('.cms-field');

		if (field) refreshField(field);
	}
}

function change(event: Event): void {
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

function focus(event: FocusEvent): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	if (target.matches(INPUT)) {
		const field = target.closest('.cms-field');

		if (field) refreshField(field);
	}

	const blockField = target.closest('[data-blocks-locale]')?.closest('.cms-field');

	if (blockField) {
		queueMicrotask(() => refreshBlockField(blockField));
	}
}

function stamp(event: Event): void {
	if (event.target instanceof Element) {
		refresh(event.target);
		const field = event.target.closest('.cms-field');

		if (field?.querySelector(BLOCK_VARIANT)) refreshBlockField(field);
	}
}

function swapped(): void {
	refresh();
}

export function install(): () => void {
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
