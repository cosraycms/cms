// One content-language selector per screen. Every translated variant stays
// in the form; selecting a language only changes which one shows, hands the
// locale to hosted element controls, and remembers the choice per browser.
// The selector may render more than once in a scope, as in the node
// inspector and its collapsed strip; every copy follows the choice.

const CONTENT_SCOPE = '[data-content-locale-scope]';
const CONTENT_CONTROL = '[data-content-locale-control]';
const CONTENT_OPTION = '[data-content-locale-option]';
const STORE = 'cosray:content-locale';

/**
 * @param {Element} scope
 * @param {string} locale
 * @param {ParentNode} [root]
 */
function show(scope, locale, root = scope) {
	/** @type {NodeListOf<HTMLElement>} */ (root.querySelectorAll('.variant[data-locale]')).forEach(
		(variant) => {
			variant.hidden = variant.dataset.locale !== locale;
		},
	);

	/** @type {NodeListOf<HTMLLabelElement>} */ (
		root.querySelectorAll('[data-locale-label-for]')
	).forEach((label) => {
		label.htmlFor = `${label.dataset.localeLabelFor}-${locale}`;
	});
}

// A host not yet upgraded reads the scope's locale when it connects; a
// property assigned before that would shadow its accessor for good.
/**
 * @param {ParentNode} root
 * @param {string} locale
 */
function handToHosts(root, locale) {
	root.querySelectorAll('cosray-host[data-translated="true"]').forEach((host) => {
		if ('locale' in host) {
			/** @type {HTMLElement & { locale: string }} */ (host).locale = locale;
		}
	});
}

/**
 * @param {HTMLElement} control
 * @returns {string[]}
 */
function locales(control) {
	if (control instanceof HTMLSelectElement) {
		return Array.from(control.options, (option) => option.value);
	}

	return Array.from(
		/** @type {NodeListOf<HTMLElement>} */ (control.querySelectorAll(CONTENT_OPTION)),
		(option) => option.dataset.contentLocaleOption ?? '',
	).filter((locale) => locale !== '');
}

/**
 * @param {HTMLElement} control
 * @returns {string}
 */
function selected(control) {
	if (control instanceof HTMLSelectElement) {
		return control.value;
	}

	return (
		/** @type {HTMLElement | null} */ (
			control.querySelector(`${CONTENT_OPTION}[aria-checked="true"]`)
		)?.dataset.contentLocaleOption ?? ''
	);
}

/**
 * @param {HTMLElement} control
 * @param {string} locale
 */
function updateControl(control, locale) {
	if (control instanceof HTMLSelectElement) {
		control.value = locale;
		return;
	}

	/** @type {NodeListOf<HTMLElement>} */ (control.querySelectorAll(CONTENT_OPTION)).forEach(
		(option) => {
			const active = option.dataset.contentLocaleOption === locale;
			option.setAttribute('aria-checked', String(active));
			option.tabIndex = active ? 0 : -1;
		},
	);
}

/** @returns {string} */
function remembered() {
	try {
		return localStorage.getItem(STORE) ?? '';
	} catch {
		return '';
	}
}

/**
 * @param {string} locale
 */
function remember(locale) {
	try {
		localStorage.setItem(STORE, locale);
	} catch {
		// A private window or blocked storage: the choice lasts for the page.
	}
}

/**
 * @param {Element} scope
 * @param {string} locale
 * @param {ParentNode} [root]
 */
export function selectContentLocale(scope, locale, root) {
	const controls = Array.from(
		/** @type {NodeListOf<HTMLElement>} */ (scope.querySelectorAll(CONTENT_CONTROL)),
	);
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

/**
 * @param {Element} scope
 */
function initializeContent(scope) {
	const control = /** @type {HTMLElement | null} */ (scope.querySelector(CONTENT_CONTROL));

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

/** @returns {Element | null} */
function single() {
	const scopes = document.querySelectorAll(CONTENT_SCOPE);

	return scopes.length === 1 ? scopes[0] : null;
}

/**
 * @param {Event} event
 */
function click(event) {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const option = /** @type {HTMLElement | null} */ (target.closest(CONTENT_OPTION));
	const scope = option?.closest(CONTENT_SCOPE);
	const locale = option?.dataset.contentLocaleOption ?? '';

	if (scope && locale !== '') {
		selectContentLocale(scope, locale);
	}
}

// A control mirrored elsewhere — inside a settings dialog, or a dialog
// mounted on the body outside every scope, which then means the screen's
// one scope — asks for the switch through this event.
/**
 * @param {Event} event
 */
function select(event) {
	const target = event.target;
	const locale = /** @type {CustomEvent<{ locale?: string }>} */ (event).detail?.locale ?? '';

	if (!(target instanceof Element) || locale === '') {
		return;
	}

	const scope = target.closest(CONTENT_SCOPE) ?? single();

	if (scope) {
		selectContentLocale(scope, locale);
	}
}

/**
 * @param {Event} event
 */
function change(event) {
	const control = event.target;

	if (!(control instanceof HTMLSelectElement) || !control.matches(CONTENT_CONTROL)) {
		return;
	}

	const scope = control.closest(CONTENT_SCOPE);

	if (scope) {
		selectContentLocale(scope, control.value);
	}
}

/**
 * @param {KeyboardEvent} event
 */
function keydown(event) {
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

	const control = /** @type {HTMLElement | null} */ (target.closest(CONTENT_CONTROL));
	const scope = target.closest(CONTENT_SCOPE);
	const options = control
		? Array.from(/** @type {NodeListOf<HTMLElement>} */ (control.querySelectorAll(CONTENT_OPTION)))
		: [];
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

/**
 * @param {Event} event
 */
function stamp(event) {
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

function initialize() {
	document.querySelectorAll(CONTENT_SCOPE).forEach(initializeContent);
}

/** @returns {() => void} */
export function install() {
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
