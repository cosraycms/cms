// Locale switching keeps every submitted variant in the form. Node editors
// use one control for the whole content tree; other panel screens retain
// their nearest data-locale-scope tabs.

const CONTENT_SCOPE = '[data-content-locale-scope]';
const CONTENT_CONTROL = '[data-content-locale-control]';
const CONTENT_OPTION = '[data-content-locale-option]';

function show(scope: Element, locale: string, root: ParentNode = scope): void {
	root.querySelectorAll<HTMLElement>('.variant[data-locale]').forEach((variant) => {
		variant.hidden = variant.dataset.locale !== locale;
	});

	root.querySelectorAll<HTMLLabelElement>('[data-locale-label-for]').forEach((label) => {
		label.htmlFor = `${label.dataset.localeLabelFor}-${locale}`;
	});
}

function handToHosts(root: ParentNode, locale: string, translatedOnly: boolean): void {
	const selector = translatedOnly ? 'cosray-host[data-translated="true"]' : 'cosray-host';

	root.querySelectorAll(selector).forEach((host) => {
		(host as HTMLElement & { locale: string }).locale = locale;
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

export function selectContentLocale(scope: Element, locale: string, root?: ParentNode): void {
	const control = scope.querySelector<HTMLElement>(CONTENT_CONTROL);

	if (!control || !locales(control).includes(locale)) {
		return;
	}

	const changed = scope.getAttribute('data-content-locale') !== locale;
	scope.setAttribute('data-content-locale', locale);
	updateControl(control, locale);
	show(scope, locale, root);
	handToHosts(root ?? scope, locale, true);

	if (changed && root === undefined) {
		control.dispatchEvent(new CustomEvent('content-locale:change', { bubbles: true }));
	}
}

function initializeContent(scope: Element): void {
	const control = scope.querySelector<HTMLElement>(CONTENT_CONTROL);
	const locale = (control && selected(control)) || scope.getAttribute('data-content-locale') || '';

	if (!control || locale === '') {
		return;
	}

	scope.setAttribute('data-content-locale', locale);
	updateControl(control, locale);
	show(scope, locale);
}

function activateLocal(tab: HTMLElement): void {
	const scope = tab.closest('[data-locale-scope]');
	const locale = tab.dataset.localeTab ?? '';

	if (!scope || locale === '') {
		return;
	}

	scope.querySelectorAll('[data-locale-tab]').forEach((other) => {
		other.classList.toggle('active', other === tab);
	});
	show(scope, locale);
	handToHosts(scope, locale, false);
}

function click(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const option = target.closest<HTMLElement>(CONTENT_OPTION);
	const optionScope = option?.closest(CONTENT_SCOPE);
	const locale = option?.dataset.contentLocaleOption ?? '';

	if (optionScope && locale !== '') {
		selectContentLocale(optionScope, locale);
		return;
	}

	const tab = target.closest('[data-locale-tab]');

	if (tab instanceof HTMLElement) {
		activateLocal(tab);
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
	document.addEventListener('repeater:stamp', stamp);
	document.addEventListener('htmx:after:swap', initialize);
	initialize();

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('change', change);
		document.removeEventListener('keydown', keydown);
		document.removeEventListener('repeater:stamp', stamp);
		document.removeEventListener('htmx:after:swap', initialize);
	};
}
