// Contract-level tests against hand-built DOM mirroring what
// panel/views/field/repeater.php renders — deliberately not snapshots of
// that view, so the entries refactor can generalize the behavior
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

function row(index: string, value = '', extra = ''): string {
	const label = /^\d+$/.test(index) ? `${Number(index) + 1}.` : '';

	return `<div data-repeater-row>
		<label for="${ID}-${index}" data-repeater-label>${label}</label>
		<input id="${ID}-${index}" name="${NAME}[${index}]" value="${value}">
		${extra}
		<button type="button" data-repeater-remove>Remove</button>
	</div>`;
}

function repeater(rows: string[], options: { max?: number; template?: string } = {}): HTMLElement {
	document.body.innerHTML = `<div
		data-repeater
		data-name="${NAME}"
		data-id="${ID}"
		${options.max === undefined ? '' : `data-max="${options.max}"`}>
		${rows.join('')}
		${options.template ?? `<template data-repeater-template>${row('__i__')}</template>`}
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

function nestedRow(outerIndex: string, value: string): string {
	const base = `${NAME}[${outerIndex}][items]`;
	const id = `${ID}-${outerIndex}-items`;

	return `<div data-repeater-row>
		<input id="${ID}-${outerIndex}" name="${NAME}[${outerIndex}]" value="${value}">
		<div data-repeater data-name="${base}" data-id="${id}">
			<div data-repeater-row>
				<input id="${id}-0" name="${base}[0]" value="x">
			</div>
			<template data-repeater-template>
				<div data-repeater-row>
					<input id="${id}-__i__" name="${base}[__i__]" value="">
				</div>
			</template>
			<div data-repeater-footer>
				<button type="button" data-repeater-add>Add nested</button>
			</div>
		</div>
		<button type="button" data-repeater-remove>Remove</button>
	</div>`;
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
		const container = repeater([row('0', 'a')], { max: 2 });
		const add = container.querySelector<HTMLElement>('[data-repeater-add]');

		click(container, '[data-repeater-add]');

		expect(container.querySelectorAll('[data-repeater-row]')).toHaveLength(2);
		expect(add?.hidden).toBe(true);

		click(container, '[data-repeater-remove]');

		expect(add?.hidden).toBe(false);
	});

	it('moves a row up and renumbers', () => {
		const container = repeater([row('0', 'a'), row('1', 'b'), row('2', 'c')]);
		const second = container.querySelectorAll<HTMLElement>('[data-repeater-row]')[1];

		second?.insertAdjacentHTML(
			'beforeend',
			'<button type="button" data-repeater-move="up">Up</button>',
		);
		click(container, '[data-repeater-move="up"]');

		expect(names(container)).toEqual([`${NAME}[0]`, `${NAME}[1]`, `${NAME}[2]`]);
		expect(Array.from(container.querySelectorAll('input'), (input) => input.value)).toEqual([
			'b',
			'a',
			'c',
		]);
	});

	it('moves a row down and renumbers', () => {
		const container = repeater([row('0', 'a'), row('1', 'b')]);
		const first = container.querySelectorAll<HTMLElement>('[data-repeater-row]')[0];

		first?.insertAdjacentHTML(
			'beforeend',
			'<button type="button" data-repeater-move="down">Down</button>',
		);
		click(container, '[data-repeater-move="down"]');

		expect(Array.from(container.querySelectorAll('input'), (input) => input.value)).toEqual([
			'b',
			'a',
		]);
	});

	it('ignores a move past the edge and stays silent', () => {
		const container = repeater([row('0', 'a'), row('1', 'b')]);
		const first = container.querySelectorAll<HTMLElement>('[data-repeater-row]')[0];

		first?.insertAdjacentHTML(
			'beforeend',
			'<button type="button" data-repeater-move="up">Up</button>',
		);

		let changes = 0;
		const count = (): void => {
			changes += 1;
		};

		document.addEventListener('change', count);
		click(container, '[data-repeater-move="up"]');
		document.removeEventListener('change', count);

		expect(changes).toBe(0);
		expect(Array.from(container.querySelectorAll('input'), (input) => input.value)).toEqual([
			'a',
			'b',
		]);
	});

	it('stamps the template matching a typed add button', () => {
		const template = `
			<template data-repeater-template="App\\Node\\Quote">
				${row('__i__', 'quote')}
			</template>
			<template data-repeater-template="App\\Node\\Person">
				${row('__i__', 'person')}
			</template>`;

		document.body.innerHTML = `<div data-repeater data-name="${NAME}" data-id="${ID}">
			${template}
			<div data-repeater-footer>
				<button type="button" data-repeater-add="App\\Node\\Person">Add person</button>
			</div>
		</div>`;

		const container = document.querySelector<HTMLElement>('[data-repeater]');

		if (!container) {
			throw new Error('container missing');
		}

		click(container, '[data-repeater-add]');

		const stamped = container.querySelector('[data-repeater-row] input');

		expect(stamped?.getAttribute('value')).toBe('person');
		expect(stamped?.getAttribute('name')).toBe(`${NAME}[0]`);
	});

	it('fills a fresh uid into stamped rows', () => {
		const template = `<template data-repeater-template>
			<div data-repeater-row>
				<input type="hidden" data-repeater-uid name="${NAME}[__i__][uid]" value="">
				<input id="${ID}-__i__" name="${NAME}[__i__][title]" value="">
			</div>
		</template>`;
		const container = repeater([], { template });

		click(container, '[data-repeater-add]');
		click(container, '[data-repeater-add]');

		const uids = Array.from(
			container.querySelectorAll<HTMLInputElement>('[data-repeater-uid]'),
			(input) => input.value,
		);

		expect(uids).toHaveLength(2);
		expect(uids[0]).toMatch(/^[0-9a-f]{8}-[0-9a-f-]{27}$/);
		expect(uids[0]).not.toBe(uids[1]);
		expect(
			container
				.querySelector('template')
				?.content.querySelector('[data-repeater-uid]')
				?.getAttribute('value'),
		).toBe('');
	});

	it('renumbers the data-name and data-id of nested containers', () => {
		const container = repeater([row('0', 'a'), nestedRow('1', 'b')]);

		container.querySelectorAll<HTMLElement>('[data-repeater-remove]')[0]?.click();

		const nested = container.querySelector<HTMLElement>('[data-repeater] [data-repeater]');

		expect(nested?.dataset.name).toBe(`${NAME}[0][items]`);
		expect(nested?.dataset.id).toBe(`${ID}-0-items`);
	});

	it('renumbers nested template content along with the row', () => {
		const container = repeater([row('0', 'a'), nestedRow('1', 'b')]);

		container.querySelectorAll<HTMLElement>('[data-repeater-remove]')[0]?.click();

		const nested = container.querySelector<HTMLElement>('[data-repeater] [data-repeater]');
		const templateInput = nested?.querySelector('template')?.content.querySelector('input');

		expect(templateInput?.name).toBe(`${NAME}[0][items][__i__]`);

		// End to end: the nested repeater must stamp correct names after
		// its row moved to a new index.
		nested?.querySelector<HTMLElement>('[data-repeater-add]')?.click();

		expect(names(nested as HTMLElement)).toEqual([`${NAME}[0][items][0]`, `${NAME}[0][items][1]`]);
	});

	it('collapses and expands a row body', () => {
		const container = repeater([
			`<div data-repeater-row>
				<button type="button" data-repeater-collapse aria-expanded="true">Title</button>
				<div data-repeater-body><input name="${NAME}[0]" value="a"></div>
			</div>`,
		]);
		const toggle = container.querySelector<HTMLElement>('[data-repeater-collapse]');
		const body = container.querySelector<HTMLElement>('[data-repeater-body]');

		toggle?.click();

		expect(body?.hidden).toBe(true);
		expect(toggle?.getAttribute('aria-expanded')).toBe('false');

		toggle?.click();

		expect(body?.hidden).toBe(false);
		expect(toggle?.getAttribute('aria-expanded')).toBe('true');
	});

	it('leaves nested add buttons alone when the outer repeater is full', () => {
		const template = `<template data-repeater-template>${nestedRow('__i__', '')}</template>`;
		const container = repeater([], { max: 1, template });

		click(container, ':scope > [data-repeater-footer] [data-repeater-add]');

		const outerAdd = container.querySelector<HTMLElement>(
			':scope > [data-repeater-footer] [data-repeater-add]',
		);
		const nestedAdd = container.querySelector<HTMLElement>(
			'[data-repeater] [data-repeater] [data-repeater-add]',
		);

		expect(outerAdd?.hidden).toBe(true);
		expect(nestedAdd?.hidden).toBe(false);
	});
});
