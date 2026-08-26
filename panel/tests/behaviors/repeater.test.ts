// Contract-level tests against hand-built DOM mirroring what
// panel/views/field/repeater.php renders — deliberately not snapshots of
// that view, so the coming entries refactor can generalize the behavior
// without rewriting these.

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/repeater';

const NAME = 'content[tags][value][zxx]';
const ID = 'field-tags';

let uninstall: (() => void) | null = null;

beforeEach(() => {
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
});

function row(index: string, value = ''): string {
	const label = /^\d+$/.test(index) ? `${Number(index) + 1}.` : '';

	return `<div data-repeater-row>
		<label for="${ID}-${index}" data-repeater-label>${label}</label>
		<input id="${ID}-${index}" name="${NAME}[${index}]" value="${value}">
		<button type="button" data-repeater-remove>Remove</button>
	</div>`;
}

function repeater(rows: string[], max?: number): HTMLElement {
	document.body.innerHTML = `<div
		data-repeater
		data-name="${NAME}"
		data-id="${ID}"
		${max === undefined ? '' : `data-max="${max}"`}>
		${rows.join('')}
		<template data-repeater-template>${row('__i__')}</template>
		<div data-repeater-footer>
			<button type="button" data-repeater-add>Add</button>
		</div>
	</div>`;

	const container = document.querySelector<HTMLElement>('[data-repeater]');

	if (!container) {
		throw new Error('container missing');
	}

	return container;
}

function names(container: HTMLElement): string[] {
	return Array.from(container.querySelectorAll('input'), (input) => input.name);
}

function click(container: HTMLElement, selector: string): void {
	container.querySelector<HTMLElement>(selector)?.click();
}

describe('repeater behavior', () => {
	it('adds a row from the template with a dense index', () => {
		const container = repeater([row('0', 'a'), row('1', 'b')]);

		click(container, '[data-repeater-add]');

		const rows = container.querySelectorAll('[data-repeater-row]');
		const added = rows[2];

		expect(rows).toHaveLength(3);
		expect(added?.querySelector('input')?.name).toBe(`${NAME}[2]`);
		expect(added?.querySelector('input')?.id).toBe(`${ID}-2`);
		expect(added?.querySelector('label')?.getAttribute('for')).toBe(`${ID}-2`);
		expect(added?.querySelector('[data-repeater-label]')?.textContent).toBe('3.');
	});

	it('renumbers densely after removing a middle row', () => {
		const container = repeater([row('0', 'a'), row('1', 'b'), row('2', 'c')]);

		container.querySelectorAll<HTMLElement>('[data-repeater-remove]')[1]?.click();

		expect(names(container)).toEqual([`${NAME}[0]`, `${NAME}[1]`]);
		expect(Array.from(container.querySelectorAll('input'), (input) => input.value)).toEqual([
			'a',
			'c',
		]);
		expect(
			Array.from(container.querySelectorAll('[data-repeater-label]'), (label) => label.textContent),
		).toEqual(['1.', '2.']);
	});

	it('dispatches a bubbling change event on structural edits', () => {
		const container = repeater([row('0', 'a')]);
		let changes = 0;
		const count = (): void => {
			changes += 1;
		};

		document.addEventListener('change', count);
		click(container, '[data-repeater-add]');
		click(container, '[data-repeater-remove]');
		document.removeEventListener('change', count);

		expect(changes).toBe(2);
	});

	it('hides the add button at max rows and reveals it after a remove', () => {
		const container = repeater([row('0', 'a')], 2);
		const add = container.querySelector<HTMLElement>('[data-repeater-add]');

		click(container, '[data-repeater-add]');

		expect(container.querySelectorAll('[data-repeater-row]')).toHaveLength(2);
		expect(add?.hidden).toBe(true);

		click(container, '[data-repeater-remove]');

		expect(add?.hidden).toBe(false);
	});

	it('renumbers input names inside nested containers', () => {
		const container = repeater([
			row('0', 'a'),
			`<div data-repeater-row>
				<input id="${ID}-1" name="${NAME}[1]" value="b">
				<div data-repeater data-name="${NAME}[1][items]" data-id="${ID}-1-items">
					<div data-repeater-row>
						<input id="${ID}-1-items-0" name="${NAME}[1][items][0]" value="x">
					</div>
				</div>
				<button type="button" data-repeater-remove>Remove</button>
			</div>`,
		]);

		container.querySelectorAll<HTMLElement>('[data-repeater-remove]')[0]?.click();

		expect(names(container)).toEqual([`${NAME}[0]`, `${NAME}[0][items][0]`]);
	});

	// Known limitation the entries refactor must fix: renumbering rewrites
	// name/id/for on descendants but not the data-name/data-id a nested
	// structural container navigates by, so the nested repeater renumbers
	// against a stale base after its parent row moves. Marked `fails` so
	// it flips loudly when the fix lands.
	it.fails('renumbers the data-name of nested containers', () => {
		const container = repeater([
			row('0', 'a'),
			`<div data-repeater-row>
				<input id="${ID}-1" name="${NAME}[1]" value="b">
				<div data-repeater data-name="${NAME}[1][items]" data-id="${ID}-1-items">
					<div data-repeater-row>
						<input id="${ID}-1-items-0" name="${NAME}[1][items][0]" value="x">
					</div>
				</div>
				<button type="button" data-repeater-remove>Remove</button>
			</div>`,
		]);

		container.querySelectorAll<HTMLElement>('[data-repeater-remove]')[0]?.click();

		const nested = container.querySelector<HTMLElement>('[data-repeater] [data-repeater]');

		expect(nested?.dataset.name).toBe(`${NAME}[0][items]`);
	});
});
