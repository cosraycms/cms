// Changing a block's type. A fresh block of the picked type takes the
// block's place with its size and settings, which deleting it and adding
// another would not keep: on the grid the rows it held close up, and in
// a split a removed part hands its space on or dissolves the split. The
// content does not carry over; a block that has any asks first.

/** @import { CosrayHost, HostPayload } from '../lib/host.js' */

import { openMenu } from '../lib/action-menu.js';

import { open as openCatalog } from './block-catalog.js';
import { adder, arm } from './pick.js';
import { placed } from './placement.js';
import { changed, copy, insert, insertion } from './repeater.js';

/**
 * @param {HTMLElement} row
 * @returns {string}
 */
function typeOf(row) {
	return (
		/** @type {HTMLInputElement | null} */ (row.querySelector(':scope > input[name$="[type]"]'))
			?.value ?? ''
	);
}

/**
 * The names of a row's own controls start with this: its uid's without `[uid]`.
 *
 * @param {ParentNode} row
 * @returns {string}
 */
function prefix(row) {
	const uid = /** @type {HTMLInputElement | null} */ (row.querySelector('[data-repeater-uid]'));

	return uid?.name.replace(/\[uid\]$/, '') ?? '';
}

/**
 * @param {HTMLElement} field
 * @param {string} type
 * @returns {HTMLTemplateElement | undefined}
 */
function template(field, type) {
	return [
		.../** @type {NodeListOf<HTMLTemplateElement>} */ (
			field.querySelectorAll(':scope > template[data-repeater-template]')
		),
	].find((candidate) => candidate.getAttribute('data-repeater-template') === type);
}

/**
 * Text anywhere in a structured value, as words or a file's uid; a node's type is none.
 *
 * @param {unknown} value
 * @returns {boolean}
 */
function holds(value) {
	if (typeof value === 'string') {
		return value.trim() !== '';
	}

	if (Array.isArray(value)) {
		return value.some(holds);
	}

	if (value !== null && typeof value === 'object') {
		return Object.entries(value).some(([key, item]) => key !== 'type' && holds(item));
	}

	return false;
}

/**
 * A custom control's value; one never connected still has its payload script.
 *
 * @param {Element} host
 * @returns {boolean}
 */
function hostHolds(host) {
	const payload = /** @type {Partial<CosrayHost>} */ (host).payload;

	if (payload) {
		return holds(payload.value);
	}

	const script = host.querySelector(':scope > script[type="application/json"]');

	try {
		return holds(
			/** @type {HostPayload | null} */ (JSON.parse(script?.textContent || 'null'))?.value,
		);
	} catch {
		// What cannot be read may well be content.
		return true;
	}
}

/**
 * Whether the block holds anything a fresh one of its type would not: a
 * typed value other than the template's, or a custom control's value
 * with text in it. Choices are settings, not content.
 *
 * @param {HTMLElement} row
 * @param {HTMLTemplateElement | undefined} fresh
 * @returns {boolean}
 */
function filled(row, fresh) {
	const own = prefix(row);
	const base = fresh ? prefix(fresh.content) : '';
	const defaults = /** @type {Map<string, string>} */ (new Map());

	/** @type {NodeListOf<HTMLInputElement | HTMLTextAreaElement>} */ (
		fresh?.content.querySelectorAll('input, textarea')
	).forEach((control) => defaults.set(control.name.slice(base.length), control.value));

	for (const control of /** @type {NodeListOf<HTMLInputElement | HTMLTextAreaElement>} */ (
		row.querySelectorAll('input, textarea')
	)) {
		const name = control.name.startsWith(own) ? control.name.slice(own.length) : '';

		if (
			!name.startsWith('[fields]') ||
			(control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type))
		) {
			continue;
		}

		if (control.value.trim() !== (defaults.get(name) ?? '').trim()) {
			return true;
		}
	}

	return [...row.querySelectorAll('cosray-host')].some(
		(host) => host.getAttribute('name')?.startsWith(`${own}[fields]`) && hostHolds(host),
	);
}

/**
 * @param {HTMLElement} row
 * @param {HTMLElement} field
 * @param {string | null} type
 * @param {string} question
 */
function retype(row, field, type, question) {
	const context = row.parentElement && insertion(row.parentElement);
	const current = typeOf(row);

	if (!context || type === current) {
		return;
	}

	if (filled(row, template(field, current)) && !window.confirm(question)) {
		return;
	}

	let stamped = false;

	insert(
		{
			...context,
			// On a grid by position the new block takes the old one's
			// cells; in a list it takes its place in the order.
			at: placed(row) ? null : { row, where: 'after' },
			prepare(clone) {
				stamped = true;
				copy(row, clone, context.owner, (name) => /^\[(layout|meta)\]/.test(name));
			},
		},
		type,
	);

	// An insertion refused, as for a content language switched meanwhile,
	// keeps the block.
	if (stamped) {
		row.remove();
		changed(context.owner);
	}
}

/**
 * The field's picker at the block's kebab; its choice replaces the block.
 *
 * @param {HTMLElement} row
 * @param {string} question
 * @param {boolean} keyboard
 */
function choose(row, question, keyboard) {
	const field = /** @type {HTMLElement | null} */ (row.closest('.cms-blocks-editor'));
	const how = field && adder(field);
	const base = field && insertion(field);
	const menu = how && 'picker' in how ? document.getElementById(how.picker) : null;
	const kebab = /** @type {HTMLButtonElement | null} */ (
		row.querySelector(':scope > .chrome .kebab')
	);

	if (!field || !base || !menu || !kebab) {
		return;
	}

	/**
	 * @param {string | null} type
	 */
	const change = (type) => retype(row, field, type, question);

	openMenu(kebab, keyboard ? 'first' : false, kebab, menu);
	arm(menu, change, () => openCatalog({ ...base, at: { row, where: 'after' } }, true, change));
}

/**
 * @param {MouseEvent} event
 */
function onClick(event) {
	const entry =
		event.target instanceof Element
			? /** @type {HTMLElement | null} */ (event.target.closest('[data-retype]'))
			: null;
	const row = /** @type {HTMLElement | null} */ (entry?.closest('[data-repeater-row]'));

	if (entry && row) {
		choose(row, entry.getAttribute('data-retype-confirm') ?? '', event.detail === 0);
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', onClick);

	return () => document.removeEventListener('click', onClick);
}
