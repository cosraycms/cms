import { closeMenu } from '../lib/action-menu.js';
import { nearestDialog } from '../lib/dialogs.js';
import { __, __n } from '../lib/locale.js';
import { selectContentLocale } from './content-locales.js';
import { revealInspector } from './inspector.js';
import { revealTab } from './tabs.js';

// Field-level validation errors for the SSR editor form.
//
// A failed save swaps the #editor-errors summary out-of-band; each issue
// carries the sire data path of the failing value in data-error-path.
// Form control names mirror that data structure (content[f][value][de]),
// so a path resolves to its control by building the name and falling
// back to ever shorter prefixes — the fallback is what finds an element
// control's host, whose single [json] leaf has no per-locale input.
//
// The issues are grouped per field into problems, re-derived whenever a
// NEW summary arrives (tracked by element identity — other swaps, like the
// route-path preview, must not repaint or steal focus). The summary's jump
// chips, the header's problem menu and the field messages all follow the
// open problems: editing a field resolves its problem, and a required
// native control left empty again opens it once more on blur. Nothing is
// validated here; the next save is what checks the values.

const BOX = 'editor-errors';
const PROBLEMS = 'editor-problems';
const FORM = 'node-editor-form';
const INVALID = 'data-invalid';
const MESSAGE = 'data-error-message';
const EMPTY = new Set(['missing', 'null', 'rule.required']);

/**
 * @typedef {object} Issue
 * @property {Element} control
 * @property {string} message
 * @property {boolean} empty The value is missing rather than invalid.
 * @property {string | undefined} locale
 */

/**
 * @typedef {object} Problem
 * @property {Element} field
 * @property {Path} path
 * @property {string} label
 * @property {Issue[]} issues
 * @property {boolean} open
 */

/** @type {Element | null} */
let lastBox = null;
let counter = 0;
/** @type {Problem[]} */
let problems = [];
/** @type {string[]} */
let general = [];
/** @type {WeakMap<Element, Problem>} */
let jumps = new WeakMap();

/** @typedef {Array<string | number>} Path */

/**
 * @param {Path} path
 * @returns {string}
 */
function nameFor(path) {
	return (
		String(path[0]) +
		path
			.slice(1)
			.map((segment) => `[${String(segment)}]`)
			.join('')
	);
}

/**
 * @param {Element} item
 * @returns {Path | null}
 */
function parsePath(item) {
	try {
		/** @type {unknown} */
		const parsed = JSON.parse(item.getAttribute('data-error-path') ?? '');

		if (
			Array.isArray(parsed) &&
			parsed.length > 0 &&
			parsed.every((segment) => typeof segment === 'string' || typeof segment === 'number')
		) {
			return /** @type {Path} */ (parsed);
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
/**
 * @param {string} name
 * @returns {string}
 */
function selectorValue(name) {
	return name.replace(/[\\"[\]]/g, '\\$&');
}

/**
 * @param {Element} form
 * @param {Path} path
 * @returns {Element | null}
 */
function resolve(form, path) {
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

/**
 * A control inside a meta dialog belongs to the dialog's owner — the field
 * wrapper, or the block row whose settings dialog took the group.
 *
 * @param {Element} control
 * @returns {Element}
 */
function wrapper(control) {
	const owner = control.closest('dialog[data-dialog="meta"]')?.closest('[data-meta-owner]');

	return owner ?? control.closest('.cms-field') ?? control.parentElement ?? control;
}

/**
 * @param {HTMLElement} control
 * @returns {Set<string>}
 */
function contentLocaleIds(control) {
	if (control instanceof HTMLSelectElement) {
		return new Set(Array.from(control.options, (option) => option.value));
	}

	return new Set(
		Array.from(
			/** @type {NodeListOf<HTMLElement>} */ (
				control.querySelectorAll('[data-content-locale-option]')
			),
			(option) => option.dataset.contentLocaleOption ?? '',
		).filter((locale) => locale !== ''),
	);
}

/**
 * @param {Element} form
 * @param {Path} path
 * @param {Element} control
 * @returns {string | undefined}
 */
function contentLocale(form, path, control) {
	const localeControl = /** @type {HTMLElement | null} */ (
		form.querySelector('[data-content-locale-control]')
	);

	if (!localeControl || path[0] !== 'content') {
		return undefined;
	}

	const ids = contentLocaleIds(localeControl);
	const variant = /** @type {HTMLElement | null} */ (control.closest('.variant[data-locale]'));

	if (variant?.dataset.locale && ids.has(variant.dataset.locale)) {
		return variant.dataset.locale;
	}

	return path
		.slice(2)
		.find(
			/** @returns {segment is string} */ (segment) =>
				typeof segment === 'string' && ids.has(segment),
		);
}

/**
 * @param {Element} form
 */
function refreshContentBadge(form) {
	const locales = /** @type {Set<string>} */ (new Set());

	form.querySelectorAll(`[${INVALID}][data-error-locales]`).forEach((field) => {
		for (const locale of field.getAttribute('data-error-locales')?.split(' ') ?? []) {
			if (locale !== '') locales.add(locale);
		}
	});

	/** @type {NodeListOf<HTMLElement>} */ (
		form.querySelectorAll('[data-content-locale-control]')
	).forEach((control) => {
		control.classList.toggle('has-error', locales.size > 0);
		control.toggleAttribute('aria-invalid', locales.size > 0);
		/** @type {NodeListOf<HTMLElement>} */ (
			control.querySelectorAll('[data-content-locale-option]')
		).forEach((option) => {
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
/**
 * @param {Element} form
 */
function refreshTabBadges(form) {
	/** @type {NodeListOf<HTMLElement>} */ (
		form.querySelectorAll('[data-tabs] [role="tab"][aria-controls]')
	).forEach((tab) => {
		const panel = document.getElementById(tab.getAttribute('aria-controls') ?? '');
		const invalid = panel?.querySelector(`[${INVALID}]`) != null;

		tab.classList.toggle('has-error', invalid);
		form.querySelector(`[data-inspector-open="${tab.id}"]`)?.classList.toggle('has-error', invalid);
	});
}

/**
 * @param {Element} field
 * @param {string | undefined} locale
 */
function addErrorLocale(field, locale) {
	if (!locale) {
		return;
	}

	const locales = new Set(field.getAttribute('data-error-locales')?.split(' ') ?? []);
	locales.delete('');
	locales.add(locale);
	field.setAttribute('data-error-locales', [...locales].join(' '));
}

/**
 * @param {Element} field
 */
function unmark(field) {
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

function wipe() {
	document.querySelectorAll(`[${INVALID}]`).forEach(unmark);
	document.querySelectorAll(`[${MESSAGE}]`).forEach((message) => message.remove());
	document.querySelectorAll('[data-content-locale-control]').forEach((control) => {
		control.classList.remove('has-error');
		control.removeAttribute('aria-invalid');
		control
			.querySelectorAll('[data-content-locale-option].has-error')
			.forEach((option) => option.classList.remove('has-error'));
		delete (/** @type {HTMLElement} */ (control).dataset.errorLocales);
	});
	document
		.querySelectorAll('[data-tabs] [role="tab"].has-error')
		.forEach((tab) => tab.classList.remove('has-error'));
	problems = [];
	general = [];
	jumps = new WeakMap();
}

/**
 * @param {Element} control
 * @returns {control is HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement}
 */
function native(control) {
	return (
		control instanceof HTMLTextAreaElement ||
		control instanceof HTMLSelectElement ||
		(control instanceof HTMLInputElement &&
			!['checkbox', 'radio', 'hidden', 'file'].includes(control.type))
	);
}

// What the field asks for: text is filled in, anything else chosen.
/**
 * @param {Element} control
 * @returns {string}
 */
function request(control) {
	const typed =
		control instanceof HTMLTextAreaElement ||
		(control instanceof HTMLInputElement &&
			!['date', 'time', 'datetime-local', 'month', 'week', 'color'].includes(control.type)) ||
		control.matches('cosray-richtext, cosray-code, cosray-link');

	return typed ? __('editor:error-fill') : __('editor:error-choose');
}

/**
 * The field's name, led by the names of the fields it sits in.
 *
 * @param {Element} control
 * @param {Path} path
 * @returns {string}
 */
function labelOf(control, path) {
	const names = [];

	for (
		let field = control.closest('.cms-field');
		field;
		field = field.parentElement?.closest('.cms-field') ?? null
	) {
		const name = field.querySelector(':scope > .label .name')?.textContent?.trim();

		if (name) {
			names.unshift(name);
		}
	}

	if (names.length > 0) {
		return names.join(' › ');
	}

	const own =
		native(control) && control.labels?.[0]
			? control.labels[0].textContent
			: control.getAttribute('aria-label');

	return (
		own?.trim() ||
		/** @type {HTMLElement | null} */ (control.closest('[data-field]'))?.dataset.field ||
		String(path.at(-1))
	);
}

// A required native control is fixed once it holds a value; for anything
// else, any edit counts, since only the server can tell.
/**
 * @param {Problem} problem
 * @returns {boolean}
 */
function checkable(problem) {
	return problem.issues.every((issue) => issue.empty && native(issue.control));
}

/**
 * @param {Problem} problem
 * @returns {boolean}
 */
function filled(problem) {
	return problem.issues.every(
		(issue) => native(issue.control) && issue.control.value.trim() !== '',
	);
}

/**
 * @param {Problem} problem
 */
function show(problem) {
	const field = problem.field;
	field.setAttribute(INVALID, 'true');

	const body = field.querySelector(':scope > .field-body') ?? field;
	/** @type {Map<string, HTMLElement>} */
	const notes = new Map();

	for (const issue of problem.issues) {
		addErrorLocale(field, issue.locale);

		const text = issue.empty ? request(issue.control) : issue.message;
		let note = notes.get(text);

		if (!note) {
			note = document.createElement('p');
			note.className = 'cms-field-error';
			note.setAttribute(MESSAGE, '');
			note.id = `cms-error-${++counter}`;
			note.textContent = text;
			notes.set(text, note);

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
		}

		if (native(issue.control)) {
			issue.control.setAttribute('aria-invalid', 'true');
			issue.control.setAttribute('aria-describedby', note.id);
		}

		// An issue inside the meta dialog is invisible until opened.
		if (issue.control.closest('dialog[data-dialog="meta"]')) {
			field.querySelector('[data-dialog-open="meta"]')?.classList.add('has-error');
		}
	}

	const form = field.closest('form');

	if (form) {
		refreshContentBadge(form);
		refreshTabBadges(form);
	}
}

/**
 * @param {string} tag
 * @param {string} [className]
 * @param {string} [text]
 * @returns {HTMLElement}
 */
function create(tag, className, text) {
	const element = document.createElement(tag);

	if (className) element.className = className;
	if (text !== undefined) element.textContent = text;

	return element;
}

/**
 * @param {Problem} problem
 * @param {HTMLElement} button
 * @returns {HTMLElement}
 */
function jumpTo(problem, button) {
	button.setAttribute('type', 'button');
	button.setAttribute('data-error-jump', '');
	jumps.set(button, problem);

	return button;
}

/**
 * @param {HTMLElement} box
 */
function renderBox(box) {
	const open = problems.filter((problem) => problem.open);
	const resolved = open.length === 0 && general.length === 0;
	const summary = box.querySelector(':scope > .summary');
	const mark = box.querySelector(':scope > .mark');

	if (!summary) {
		return;
	}

	box.classList.toggle('is-resolved', resolved);

	if (mark) {
		mark.textContent = resolved ? '✓' : '!';
	}

	summary.querySelector(':scope > .title')?.toggleAttribute('hidden', resolved);
	summary.querySelectorAll(':scope > :not(.title)').forEach((element) => element.remove());

	if (resolved) {
		summary.append(create('p', 'lead', __('editor:errors-resolved')));

		return;
	}

	if (open.length > 0) {
		const empty = open.every((problem) => problem.issues.every((issue) => issue.empty));
		const lead = empty
			? __n('editor:errors-empty', 'editor:errors-empty-plural', open.length)
			: __n('editor:errors-invalid', 'editor:errors-invalid-plural', open.length);
		const chips = create('ul', 'chips');

		for (const problem of open) {
			const chip = jumpTo(problem, create('button', 'chip', `${problem.label} `));
			chip.append(create('span', 'arrow', '→'));
			chip.lastElementChild?.setAttribute('aria-hidden', 'true');

			const item = create('li');
			item.append(chip);
			chips.append(item);
		}

		summary.append(create('p', 'lead', lead), chips);
	}

	if (general.length > 0) {
		const list = create('ul', 'general');

		for (const message of general) {
			list.append(create('li', undefined, message));
		}

		summary.append(list);
	}
}

function renderProblems() {
	const holder = document.getElementById(PROBLEMS);

	if (!holder) {
		return;
	}

	const open = problems.filter((problem) => problem.open);
	const total = open.length + general.length;
	const menu = holder.querySelector('[popover]');

	holder.hidden = total === 0;

	if (total === 0 && menu instanceof HTMLElement) {
		closeMenu(menu);
	}

	const count = holder.querySelector('.count');

	if (count) {
		count.textContent = __n('editor:problems', 'editor:problems-plural', total);
	}

	const entries = holder.querySelector('.entries');

	if (!entries) {
		return;
	}

	entries.replaceChildren();

	for (const problem of open) {
		const short = problem.issues.every((issue) => issue.empty)
			? __('editor:error-missing')
			: __('editor:error-invalid');
		const text = create('span', 'text');
		text.append(create('strong', undefined, problem.label), ` – ${short}`);
		const arrow = create('span', 'arrow', '→');
		arrow.setAttribute('aria-hidden', 'true');
		const button = jumpTo(problem, create('button'));
		button.append(text, arrow);
		entries.append(button);
	}

	for (const message of general) {
		entries.append(create('p', 'general', message));
	}
}

function render() {
	const box = document.getElementById(BOX);

	if (box instanceof HTMLElement && !box.hidden) {
		renderBox(box);
	}

	renderProblems();
}

/**
 * @param {Element | null} box
 */
function paint(box) {
	wipe();

	const form = document.getElementById(FORM);

	if (!(box instanceof HTMLElement) || box.hidden || !form) {
		renderProblems();

		return;
	}

	box.querySelectorAll('.issues > li').forEach((item) => {
		const button = item.querySelector('[data-error-path]');
		const message = (button ?? item).textContent?.trim() ?? '';
		const path = button && parsePath(button);
		const control = path && resolve(form, path);

		if (!control || !path) {
			if (message !== '') general.push(message);

			return;
		}

		const field = wrapper(control);
		/** @type {Issue} */
		const issue = {
			control,
			message,
			empty: EMPTY.has(button?.getAttribute('data-error-code') ?? ''),
			locale: contentLocale(form, path, control),
		};
		const known = problems.find((problem) => problem.field === field);

		if (known) {
			known.issues.push(issue);
		} else {
			problems.push({ field, path, label: labelOf(control, path), issues: [issue], open: true });
		}
	});

	problems.forEach(show);
	render();

	// The error-summary pattern: the box announces itself as an alert while
	// the first field that can take focus as it stands gets it.
	const first = problems[0]?.issues[0]?.control;

	if (first instanceof HTMLElement && first.checkVisibility()) {
		jump(/** @type {Problem} */ (problems[0]));

		if (problems[0]?.field.contains(document.activeElement)) {
			return;
		}
	}

	box.focus();
}

function swapped() {
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
/**
 * @param {Problem} problem
 */
function jump(problem) {
	const form = document.getElementById(FORM);
	const issue = problem.issues[0];

	if (!form || !issue) {
		return;
	}

	const control = issue.control;
	const field = problem.field;
	const globalScope =
		form.closest('[data-content-locale-scope]') ??
		form.querySelector('[data-content-locale-scope]');

	if (globalScope && issue.locale) {
		selectContentLocale(globalScope, issue.locale);
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

	const dialog = control.closest('dialog[data-dialog]');

	if (dialog instanceof HTMLDialogElement && !dialog.open) {
		const triggers = dialog.parentElement?.querySelectorAll('[data-dialog-open]') ?? [];
		const trigger = [...triggers].find(
			(element) =>
				element instanceof HTMLElement &&
				nearestDialog(element, element.dataset.dialogOpen ?? '') === dialog,
		);

		/** @type {HTMLElement | undefined} */ (trigger)?.click();
	}

	if (field instanceof HTMLElement) {
		field.scrollIntoView?.({ block: 'center' });
	}

	const focus = control.closest('[data-youtube-entry]')?.hasAttribute('hidden')
		? control.closest('[data-youtube]')?.querySelector('[data-youtube-replace]')
		: control;

	if (focus instanceof HTMLElement) {
		focus.focus({ preventScroll: true });
	}
}

/**
 * @param {Event} event
 */
function activate(event) {
	const target = event.target;
	const button = target instanceof Element ? target.closest('[data-error-jump]') : null;
	const problem = button && jumps.get(button);

	if (!button || !problem) {
		return;
	}

	// Closed first: the menu would hand focus back to its trigger.
	const menu = button.closest('[popover]');

	if (menu instanceof HTMLElement) {
		closeMenu(menu);
	}

	jump(problem);
}

/**
 * The innermost open problem whose field holds the target.
 *
 * @param {EventTarget | null} target
 * @param {boolean} open
 * @returns {Problem | undefined}
 */
function problemAt(target, open) {
	for (
		let element = target instanceof Element ? target : null;
		element;
		element = element.parentElement
	) {
		const found = problems.find((problem) => problem.field === element && problem.open === open);

		if (found) {
			return found;
		}
	}

	return undefined;
}

/**
 * @param {Event} event
 */
function edit(event) {
	const problem = problemAt(event.target, true);

	if (!problem || (checkable(problem) && !filled(problem))) {
		return;
	}

	problem.open = false;
	unmark(problem.field);
	problems.filter((other) => other.open && problem.field.contains(other.field)).forEach(show);
	render();
}

/**
 * @param {Event} event
 */
function leave(event) {
	const problem = problemAt(event.target, false);

	if (!problem || !checkable(problem) || filled(problem)) {
		return;
	}

	problem.open = true;
	show(problem);
	render();
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('htmx:after:swap', swapped);
	document.addEventListener('click', activate);
	document.addEventListener('input', edit);
	document.addEventListener('change', edit);
	document.addEventListener('cosray-change', edit);
	document.addEventListener('focusout', leave);

	return () => {
		document.removeEventListener('htmx:after:swap', swapped);
		document.removeEventListener('click', activate);
		document.removeEventListener('input', edit);
		document.removeEventListener('change', edit);
		document.removeEventListener('cosray-change', edit);
		document.removeEventListener('focusout', leave);
		lastBox = null;
		problems = [];
		general = [];
	};
}
