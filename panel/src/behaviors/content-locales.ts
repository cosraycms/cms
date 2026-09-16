// One content-language selector per screen. Every translated variant stays
// in the form; selecting a language only changes which one shows, hands the
// locale to hosted element controls, and remembers the choice per browser.
// The selector may render more than once in a scope, as in the node
// inspector and its collapsed strip; every copy follows the choice.

const CONTENT_SCOPE = '[data-content-locale-scope]';
const CONTENT_CONTROL = '[data-content-locale-control]';
const CONTENT_OPTION = '[data-content-locale-option]';
const STORE = 'cosray:content-locale';

function show(scope: Element, locale: string, root: ParentNode = scope): void {
	root.querySelectorAll<HTMLElement>('.variant[data-locale]').forEach((variant) => {
		variant.hidden = variant.dataset.locale !== locale;
	});

	root.querySelectorAll<HTMLLabelElement>('[data-locale-label-for]').forEach((label) => {
		label.htmlFor = `${label.dataset.localeLabelFor}-${locale}`;
	});
}

// A host not yet upgraded reads the scope's locale when it connects; a
// property assigned before that would shadow its accessor for good.
function handToHosts(root: ParentNode, locale: string): void {
	root.querySelectorAll('cosray-host[data-translated="true"]').forEach((host) => {
		if ('locale' in host) {
			(host as HTMLElement & { locale: string }).locale = locale;
		}
	});
}

function locales(control: HTMLElement): string[] {
	if (control instanceof HTMLSelectElement) {
		return Array.from(control.options, (option) => option.value);
	}

	return Array.from(
		control.querySelectorAll<HTMLElement>(CONTENT_OPTION),
		(option) => option.dataset.contentLocaleOption ?? '',
	).filter((locale) => locale !== '');
}

function selected(control: HTMLElement): string {
	if (control instanceof HTMLSelectElement) {
		return control.value;
	}

	return (
		control.querySelector<HTMLElement>(`${CONTENT_OPTION}[aria-checked="true"]`)?.dataset
			.contentLocaleOption ?? ''
	);
}

function updateControl(control: HTMLElement, locale: string): void {
	if (control instanceof HTMLSelectElement) {
		control.value = locale;
		return;
	}

	control.querySelectorAll<HTMLElement>(CONTENT_OPTION).forEach((option) => {
		const active = option.dataset.contentLocaleOption === locale;
		option.setAttribute('aria-checked', String(active));
		option.tabIndex = active ? 0 : -1;
	});
}

function remembered(): string {
	try {
		return localStorage.getItem(STORE) ?? '';
	} catch {
		return '';
	}
}

function remember(locale: string): void {
	try {
		localStorage.setItem(STORE, locale);
	} catch {
		// A private window or blocked storage: the choice lasts for the page.
	}
}

export function selectContentLocale(scope: Element, locale: string, root?: ParentNode): void {
	const controls = Array.from(scope.querySelectorAll<HTMLElement>(CONTENT_CONTROL));
	const control = controls[0];

	if (!control || !locales(control).includes(locale)) {
		return;
	}

	const changed = scope.getAttribute('data-content-locale') !== locale;
	scope.setAttribute('data-content-locale', locale);
	controls.forEach((copy) => updateControl(copy, locale));
	show(scope, locale, root);
	handToHosts(root ?? scope, locale);

	if (root !== undefined) {
		return;
	}

	remember(locale);

	if (changed) {
		control.dispatchEvent(new CustomEvent('content-locale:change', { bubbles: true }));
	}
}

function initializeContent(scope: Element): void {
	const control = scope.querySelector<HTMLElement>(CONTENT_CONTROL);

	if (!control) {
		return;
	}

	const stored = remembered();
	const locale = locales(control).includes(stored)
		? stored
		: selected(control) || scope.getAttribute('data-content-locale') || '';

	if (locale !== '') {
		selectContentLocale(scope, locale);
	}
}

function single(): Element | null {
	const scopes = document.querySelectorAll(CONTENT_SCOPE);

	return scopes.length === 1 ? scopes[0] : null;
}

function click(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const option = target.closest<HTMLElement>(CONTENT_OPTION);
	const scope = option?.closest(CONTENT_SCOPE);
	const locale = option?.dataset.contentLocaleOption ?? '';

	if (scope && locale !== '') {
		selectContentLocale(scope, locale);
	}
}

// A control mirrored elsewhere — inside a settings dialog, or a dialog
// mounted on the body outside every scope, which then means the screen's
// one scope — asks for the switch through this event.
function select(event: Event): void {
	const target = event.target;
	const locale = (event as CustomEvent<{ locale?: string }>).detail?.locale ?? '';

	if (!(target instanceof Element) || locale === '') {
		return;
	}

	const scope = target.closest(CONTENT_SCOPE) ?? single();

	if (scope) {
		selectContentLocale(scope, locale);
	}
}

function change(event: Event): void {
	const control = event.target;

	if (!(control instanceof HTMLSelectElement) || !control.matches(CONTENT_CONTROL)) {
		return;
	}

	const scope = control.closest(CONTENT_SCOPE);

	if (scope) {
		selectContentLocale(scope, control.value);
	}
}

function keydown(event: KeyboardEvent): void {
	const target = event.target;

	if (
		event.altKey ||
		event.ctrlKey ||
		event.metaKey ||
		event.shiftKey ||
		!(target instanceof HTMLElement) ||
		!target.matches(CONTENT_OPTION)
	) {
		return;
	}

	const control = target.closest<HTMLElement>(CONTENT_CONTROL);
	const scope = target.closest(CONTENT_SCOPE);
	const options = control ? Array.from(control.querySelectorAll<HTMLElement>(CONTENT_OPTION)) : [];
	const index = options.indexOf(target);
	let next = -1;

	if (event.key === 'Home') {
		next = 0;
	} else if (event.key === 'End') {
		next = options.length - 1;
	} else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
		next = (index - 1 + options.length) % options.length;
	} else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
		next = (index + 1) % options.length;
	}

	const option = options[next];
	const locale = option?.dataset.contentLocaleOption ?? '';

	if (!scope || locale === '') {
		return;
	}

	event.preventDefault();
	selectContentLocale(scope, locale);
	option.focus();
}

function stamp(event: Event): void {
	const row = event.target;

	if (!(row instanceof Element)) {
		return;
	}

	const scope = row.closest(CONTENT_SCOPE);
	const locale = scope?.getAttribute('data-content-locale') ?? '';

	if (scope && locale !== '') {
		selectContentLocale(scope, locale, row);
	}
}

function initialize(): void {
	document.querySelectorAll(CONTENT_SCOPE).forEach(initializeContent);
}

export function install(): () => void {
	document.addEventListener('click', click);
	document.addEventListener('change', change);
	document.addEventListener('keydown', keydown);
	document.addEventListener('content-locale:select', select);
	document.addEventListener('repeater:stamp', stamp);
	document.addEventListener('htmx:after:swap', initialize);
	initialize();

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('change', change);
		document.removeEventListener('keydown', keydown);
		document.removeEventListener('content-locale:select', select);
		document.removeEventListener('repeater:stamp', stamp);
		document.removeEventListener('htmx:after:swap', initialize);
	};
}
