// Changing a block's type. A fresh block of the picked type takes the
// block's place with its size and settings, which deleting it and adding
// another would not keep: on the grid the rows it held close up, and in
// a split a removed part hands its space on or dissolves the split. The
// content does not carry over; a block that has any asks first.

import { openMenu } from '$lib/action-menu';
import type { CosrayHost, HostPayload } from '$lib/host';
import { open as openCatalog } from './block-catalog';
import { adder, arm } from './pick';
import { placed } from './placement';
import { changed, copy, insert, insertion } from './repeater';

function typeOf(row: HTMLElement): string {
	return row.querySelector<HTMLInputElement>(':scope > input[name$="[type]"]')?.value ?? '';
}

/** The names of a row's own controls start with this: its uid's without `[uid]`. */
function prefix(row: ParentNode): string {
	const uid = row.querySelector<HTMLInputElement>('[data-repeater-uid]');

	return uid?.name.replace(/\[uid\]$/, '') ?? '';
}

function template(field: HTMLElement, type: string): HTMLTemplateElement | undefined {
	return [
		...field.querySelectorAll<HTMLTemplateElement>(':scope > template[data-repeater-template]'),
	].find((candidate) => candidate.getAttribute('data-repeater-template') === type);
}

/** Text anywhere in a structured value, as words or a file's uid; a node's type is none. */
function holds(value: unknown): boolean {
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

/** A custom control's value; one never connected still has its payload script. */
function hostHolds(host: Element): boolean {
	const payload = (host as Partial<CosrayHost>).payload;

	if (payload) {
		return holds(payload.value);
	}

	const script = host.querySelector(':scope > script[type="application/json"]');

	try {
		return holds((JSON.parse(script?.textContent || 'null') as HostPayload | null)?.value);
	} catch {
		// What cannot be read may well be content.
		return true;
	}
}

/**
 * Whether the block holds anything a fresh one of its type would not: a
 * typed value other than the template's, or a custom control's value
 * with text in it. Choices are settings, not content.
 */
function filled(row: HTMLElement, fresh: HTMLTemplateElement | undefined): boolean {
	const own = prefix(row);
	const base = fresh ? prefix(fresh.content) : '';
	const defaults = new Map<string, string>();

	fresh?.content
		.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('input, textarea')
		.forEach((control) => defaults.set(control.name.slice(base.length), control.value));

	for (const control of row.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>(
		'input, textarea',
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

function retype(row: HTMLElement, field: HTMLElement, type: string | null, question: string): void {
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

/** The field's picker at the block's kebab; its choice replaces the block. */
function choose(row: HTMLElement, question: string, keyboard: boolean): void {
	const field = row.closest<HTMLElement>('.cms-blocks-editor');
	const how = field && adder(field);
	const base = field && insertion(field);
	const menu = how && 'picker' in how ? document.getElementById(how.picker) : null;
	const kebab = row.querySelector<HTMLButtonElement>(':scope > .chrome .kebab');

	if (!field || !base || !menu || !kebab) {
		return;
	}

	const change = (type: string | null) => retype(row, field, type, question);

	openMenu(kebab, keyboard ? 'first' : false, kebab, menu);
	arm(menu, change, () => openCatalog({ ...base, at: { row, where: 'after' } }, true, change));
}

function onClick(event: MouseEvent): void {
	const entry =
		event.target instanceof Element ? event.target.closest<HTMLElement>('[data-retype]') : null;
	const row = entry?.closest<HTMLElement>('[data-repeater-row]');

	if (entry && row) {
		choose(row, entry.getAttribute('data-retype-confirm') ?? '', event.detail === 0);
	}
}

export function install(): () => void {
	document.addEventListener('click', onClick);

	return () => document.removeEventListener('click', onClick);
}
