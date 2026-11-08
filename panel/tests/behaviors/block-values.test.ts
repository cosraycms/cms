import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { afterEach, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/block-values';
import { install as installRepeater } from '../../src/behaviors/repeater';

vi.mock('sortablejs', () => ({ default: vi.fn() }));

const TYPE = 'Acme\\Block\\Button';
const NAME = 'content[body][value][zxx]';
let stop: (() => void) | undefined;

// The Heading view stands in for a plugin's: any editor view gets the values.
function markup(): string {
	return execFileSync('php', [resolve('../tests/Fixtures/Panel/field.php')], {
		encoding: 'utf8',
		input: JSON.stringify({
			field: {
				name: 'body',
				label: 'Body',
				control: {
					name: 'blocks',
					props: {
						blockTypes: [
							{
								type: TYPE,
								label: 'Button',
								editor: 'field/blocks/types/heading',
								fields: [
									{
										name: 'text',
										label: 'Label',
										translate: true,
										control: { name: 'text' },
									},
									{
										name: 'level',
										label: 'Level',
										options: ['1', '2', '3'],
										control: { name: 'option' },
									},
									{
										name: 'fillColour',
										label: 'Colour',
										settings: true,
										control: { name: 'color' },
									},
									{
										name: 'wide',
										label: 'Wide',
										settings: true,
										control: { name: 'checkbox' },
									},
								],
							},
						],
					},
				},
			},
			data: {
				value: {
					zxx: [
						{
							uid: 'button1',
							type: TYPE,
							fields: {
								text: { value: { en: 'Download', de: '' } },
								level: { value: { zxx: '2' } },
								fillColour: { value: { zxx: '#e6007e' } },
								wide: { value: { zxx: true } },
							},
						},
					],
				},
			},
			locales: [
				{ id: 'en', title: 'English' },
				{ id: 'de', title: 'Deutsch' },
			],
			defaultLocale: 'en',
		}),
	});
}

function setup(): HTMLFormElement {
	document.body.innerHTML = `<form>${markup()}</form>`;
	const stops = [installRepeater(), install()];
	stop = () => stops.reverse().forEach((cleanup) => cleanup());
	return document.querySelector('form')!;
}

function body(index = 0): HTMLElement {
	return document.querySelectorAll<HTMLElement>('[data-repeater-row] > [data-block-values]')[index];
}

function control<T extends Element>(form: HTMLFormElement, sub: string, index = 0): T {
	return form.querySelector<T>(
		`[name="${NAME}[${index}][fields][${sub}][value][zxx]"]:not([type="hidden"]), [name="${NAME}[${index}][fields][${sub}][value][zxx]"][data-color-value]`,
	)!;
}

afterEach(() => {
	stop?.();
	stop = undefined;
	document.body.innerHTML = '';
});

it('renders the neutral values of a block with an editor view on its body', () => {
	setup();

	expect(body().dataset.blockValues).toBe('level fillColour wide');
	expect(body().dataset.valueLevel).toBe('2');
	expect(body().dataset.valueFillColour).toBe('#e6007e');
	expect(body().dataset.valueWide).toBe('true');
	expect(body().style.getPropertyValue('--value-fill-colour')).toBe('#e6007e');
	// Translated fields show on the canvas themselves.
	expect(body().hasAttribute('data-value-text')).toBe(false);
});

it('follows a changed value', () => {
	const form = setup();
	const level = control<HTMLSelectElement>(form, 'level');
	level.value = '3';
	level.dispatchEvent(new Event('change', { bubbles: true }));

	const wide = form.querySelector<HTMLInputElement>(
		`[name="${NAME}[0][fields][wide][value][zxx]"][type="checkbox"]`,
	)!;
	wide.click();

	const colour = control<HTMLInputElement>(form, 'fillColour');
	colour.value = '';
	colour.dispatchEvent(new Event('input', { bubbles: true }));

	expect(body().dataset.valueLevel).toBe('3');
	expect(body().dataset.valueWide).toBe('false');
	expect(body().dataset.valueFillColour).toBe('');
	expect(body().style.getPropertyValue('--value-fill-colour')).toBe('');
});

it('reads the values of a freshly added block from its controls', () => {
	const form = setup();
	form
		.querySelector<HTMLButtonElement>(`[data-repeater-add="${TYPE.replaceAll('\\', '\\\\')}"]`)
		?.click();
	// The first add button inserts above the existing block.
	expect(body(0).dataset.valueLevel).toBe(control<HTMLSelectElement>(form, 'level', 0).value);
	expect(body(0).dataset.valueWide).toBe('false');
	expect(body(1).dataset.valueWide).toBe('true');
});
