import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { afterEach, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/heading';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { install as installMenus, openMenu } from '../../src/lib/action-menu';

vi.mock('sortablejs', () => ({ default: vi.fn() }));

const TYPE = 'Cosray\\Block\\Heading';
const NAME = 'content[body][value][zxx]';
let stop: (() => void) | undefined;

function markup(level = '3', immutable = false): string {
	return execFileSync('php', [resolve('../tests/Fixtures/Panel/field.php')], {
		encoding: 'utf8',
		input: JSON.stringify({
			field: {
				name: 'body',
				label: 'Body',
				immutable,
				control: {
					name: 'blocks',
					props: {
						blockTypes: [
							{
								type: TYPE,
								label: 'Heading',
								fields: [
									{
										name: 'text',
										label: 'Heading text',
										translate: true,
										placeholder: 'Heading...',
										control: { name: 'text' },
									},
									{
										name: 'level',
										label: 'Level',
										options: ['1', '2', '3', '4', '5', '6'],
										control: { name: 'option' },
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
							uid: 'heading1',
							type: TYPE,
							fields: {
								text: { value: { en: 'A heading', de: '' } },
								level: { value: { zxx: level } },
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

function setup(level = '3', immutable = false): HTMLFormElement {
	document.body.innerHTML = `<form>${markup(level, immutable)}</form>`;
	const stops = [installMenus(), installRepeater(), install()];
	stop = () => stops.reverse().forEach((cleanup) => cleanup());
	return document.querySelector('form')!;
}

function headings(): HTMLElement[] {
	return [...document.querySelectorAll<HTMLElement>('[data-heading]')];
}

function trigger(heading: HTMLElement): HTMLButtonElement {
	return heading.querySelector<HTMLButtonElement>('.level-button')!;
}

function menu(heading: HTMLElement): HTMLElement {
	return document.getElementById(trigger(heading).getAttribute('popovertarget')!)!;
}

function choice(heading: HTMLElement, level: string): HTMLButtonElement {
	return heading.querySelector<HTMLButtonElement>(`[data-heading-level="${level}"]`)!;
}

function key(target: Element, key: string): void {
	target.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));
}

async function open(heading: HTMLElement): Promise<void> {
	vi.spyOn(trigger(heading), 'getBoundingClientRect').mockReturnValue(
		new DOMRect(150, 120, 40, 30),
	);
	openMenu(trigger(heading));
	await Promise.resolve();
}

function value(form: HTMLFormElement, index = 0): FormDataEntryValue | null {
	return new FormData(form).get(`${NAME}[${index}][fields][level][value][zxx]`);
}

afterEach(() => {
	stop?.();
	stop = undefined;
	document.body.innerHTML = '';
});

it('changes every heading level without changing its translated text', async () => {
	const form = setup();
	const heading = headings()[0];
	const changes = vi.fn();
	form.addEventListener('change', changes);
	expect(trigger(heading).textContent?.trim()).toBe('H3');
	expect(choice(heading, '3').getAttribute('aria-checked')).toBe('true');

	for (const level of ['1', '2', '3', '4', '5', '6']) {
		await open(heading);
		choice(heading, level).click();
		expect(value(form)).toBe(level);
		expect(trigger(heading).textContent?.trim()).toBe(`H${level}`);
		expect([...heading.querySelectorAll('[aria-checked="true"]')]).toEqual([
			choice(heading, level),
		]);
		expect(heading.querySelector('.is-active')).toBe(choice(heading, level));
		expect(menu(heading).matches(':popover-open')).toBe(false);
		expect(document.activeElement).toBe(trigger(heading));
	}

	expect(changes).toHaveBeenCalledTimes(6);
	expect(new FormData(form).get(`${NAME}[0][fields][text][value][en]`)).toBe('A heading');
	expect(new FormData(form).get(`${NAME}[0][fields][text][value][de]`)).toBe('');
});

it('supports keyboard selection and Escape without changing the current level', async () => {
	const form = setup();
	const heading = headings()[0];
	await open(heading);
	key(document.activeElement!, 'End');
	expect(document.activeElement).toBe(choice(heading, '6'));
	key(document.activeElement!, 'Enter');
	expect(value(form)).toBe('6');
	expect(document.activeElement).toBe(trigger(heading));

	await open(heading);
	key(document.activeElement!, 'ArrowDown');
	key(document.activeElement!, 'Escape');
	expect(value(form)).toBe('6');
	expect(document.activeElement).toBe(trigger(heading));
});

it('does not mark the form changed when choosing the current level', async () => {
	const form = setup();
	const heading = headings()[0];
	const changes = vi.fn();
	form.addEventListener('change', changes);
	await open(heading);
	choice(heading, '3').click();
	expect(changes).not.toHaveBeenCalled();
	expect(menu(heading).matches(':popover-open')).toBe(false);
});

it('keeps copied levels and independent menu targets when rows are duplicated or added', () => {
	const form = setup('4');
	choice(headings()[0], '6').click();
	document.querySelector<HTMLButtonElement>('[data-repeater-duplicate]')!.click();
	const copy = headings()[1];

	expect(value(form, 1)).toBe('6');
	expect(trigger(copy).textContent?.trim()).toBe('H6');
	expect(choice(copy, '6').getAttribute('aria-checked')).toBe('true');
	expect(menu(copy)).not.toBe(menu(headings()[0]));
	choice(copy, '2').click();
	expect(value(form, 0)).toBe('6');
	expect(value(form, 1)).toBe('2');

	document.querySelector<HTMLButtonElement>('[data-repeater-footer] [data-repeater-add]')!.click();
	const added = headings()[2];
	expect(value(form, 2)).toBe('1');
	expect(trigger(added).textContent?.trim()).toBe('H1');
	expect(choice(added, '1').getAttribute('aria-checked')).toBe('true');
	expect(document.activeElement).toBe(added.querySelector('.text input'));
});

it('locks the level of a read-only blocks field', () => {
	setup('4', true);
	const heading = headings()[0];
	expect(trigger(heading).disabled).toBe(true);
	choice(heading, '1').click();
	expect(trigger(heading).textContent?.trim()).toBe('H4');
	expect(heading.querySelector('select')?.value).toBe('4');
});

it('handles a heading arriving through a navigation swap without reinstallation', () => {
	const form = setup('2');
	form.innerHTML = markup('5');
	choice(headings()[0], '1').click();
	expect(value(form)).toBe('1');
	expect(trigger(headings()[0]).textContent?.trim()).toBe('H1');
});
