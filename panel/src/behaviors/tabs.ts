// Locale switching keeps every submitted variant in the form. Node editors
// use one selector for the whole content tree; other panel screens retain
// their nearest data-locale-scope tabs.

const CONTENT_SCOPE = '[data-content-locale-scope]';

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

export function selectContentLocale(scope: Element, locale: string, root?: ParentNode): void {
	const select = scope.querySelector<HTMLSelectElement>('[data-content-locale-select]');

	if (!select || !Array.from(select.options).some((option) => option.value === locale)) {
		return;
	}

	scope.setAttribute('data-content-locale', locale);
	select.value = locale;
	show(scope, locale, root);
	handToHosts(root ?? scope, locale, true);
}

function initializeContent(scope: Element): void {
	const select = scope.querySelector<HTMLSelectElement>('[data-content-locale-select]');
	const locale = select?.value || scope.getAttribute('data-content-locale') || '';

	if (locale === '') {
		return;
	}

	scope.setAttribute('data-content-locale', locale);
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

	const tab = target.closest('[data-locale-tab]');

	if (tab instanceof HTMLElement) {
		activateLocal(tab);
	}
}

function change(event: Event): void {
	const select = event.target;

	if (!(select instanceof HTMLSelectElement) || !select.matches('[data-content-locale-select]')) {
		return;
	}

	const scope = select.closest(CONTENT_SCOPE);

	if (scope) {
		selectContentLocale(scope, select.value);
	}
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
	document.addEventListener('repeater:stamp', stamp);
	document.addEventListener('htmx:after:swap', initialize);
	initialize();

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('change', change);
		document.removeEventListener('repeater:stamp', stamp);
		document.removeEventListener('htmx:after:swap', initialize);
	};
}
