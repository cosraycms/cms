import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/block-catalog';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { install as installBlocks } from '../../src/behaviors/blocks';
import { install as installTabs, selectContentLocale } from '../../src/behaviors/tabs';
import { install as installFallbacks } from '../../src/behaviors/fallbacks';
import { install as installMenus, openMenu } from '../../src/lib/action-menu';
import { installBridge } from '../../src/lib/bridge-standalone';
import { nest } from '../../src/lib/form-json';

vi.mock('sortablejs', () => ({ default: vi.fn() }));

const types = ['RichText', 'Text', 'Heading', 'Image', 'Images', 'Video', 'Youtube', 'Iframe'].map(
	(name) => ({
		type: `Cosray\\Block\\${name}`,
		handle: name.toLowerCase(),
		label: name === 'Heading' ? 'Überschrift mit einem langen übersetzten Namen' : name,
		fields: [{ name: 'text', control: { name: 'text' } }],
	}),
);
let stop: () => void;

function field(
	name = 'story',
	commonTypes?: string[],
	translateMode?: string,
	blockTypes = types,
): string {
	return execFileSync('php', [resolve('../tests/Fixtures/panel/field.php')], {
		encoding: 'utf8',
		input: JSON.stringify({
			field: {
				name,
				label: name,
				translate: !!translateMode,
				translateMode,
				control: {
					name: 'blocks',
					props: { blockTypes, ...(commonTypes ? { commonTypes } : {}), columns: 12, min: 2 },
				},
			},
			data: { value: { zxx: [], en: [], de: [] } },
			locales: [
				{ id: 'en', title: 'English' },
				{ id: 'de', title: 'Deutsch', fallback: 'en' },
			],
			defaultLocale: 'en',
			globalLocales: true,
		}),
	});
}

function setup(html = field()): HTMLFormElement {
	document.body.innerHTML = `<form id="node-editor-form" data-content-locale-scope data-content-locale="en" data-content-locales='[{"id":"en","title":"English"},{"id":"de","title":"Deutsch","fallback":"en"}]'>
	<select data-content-locale-control><option value="en">English</option><option value="de">Deutsch</option></select>${html}</form>`;
	const stops = [
		installMenus(),
		installRepeater(),
		installBlocks(),
		installTabs(),
		installFallbacks(),
		install(),
	];
	stop = () => stops.reverse().forEach((cleanup) => cleanup());
	return document.querySelector('form')!;
}

function owner(root: ParentNode = document): HTMLElement {
	return root.querySelector<HTMLElement>('[data-repeater]')!;
}
function rows(root: ParentNode = owner()): HTMLElement[] {
	return [
		...root.querySelectorAll<HTMLElement>(':scope > [data-repeater-list] > [data-repeater-row]'),
	];
}
function footer(root: ParentNode = owner()): HTMLElement {
	return root.querySelector<HTMLElement>(':scope > [data-repeater-footer]')!;
}
function quick(root: ParentNode = footer(), index = 1): void {
	root.querySelectorAll<HTMLButtonElement>('[data-repeater-add]')[index].click();
}
function open(root: ParentNode = footer()): HTMLDialogElement {
	const trigger = root.querySelector<HTMLButtonElement>('button[popovertarget]')!;
	vi.spyOn(trigger, 'getBoundingClientRect').mockReturnValue(new DOMRect(150, 120, 80, 30));
	openMenu(trigger, 'first');
	root.querySelector<HTMLButtonElement>('[data-block-catalog-open]')!.click();
	return document.querySelector('dialog[open]')!;
}
function search(dialog: ParentNode, value: string): HTMLInputElement {
	const input = dialog.querySelector<HTMLInputElement>('[data-block-search]')!;
	input.value = value;
	input.dispatchEvent(new Event('input', { bubbles: true }));
	return input;
}
function choices(dialog: ParentNode): HTMLButtonElement[] {
	return [...dialog.querySelectorAll<HTMLButtonElement>('[data-block-choice]:not([hidden])')];
}
function key(target: Element, key: string): KeyboardEvent {
	const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true });
	target.dispatchEvent(event);
	return event;
}
function cancel(dialog: HTMLDialogElement): void {
	dialog.dispatchEvent(new Event('cancel', { cancelable: true }));
}

beforeEach(() => {
	installBridge({
		locale: 'en',
		defaultLocale: 'en',
		locales: [],
		customLocales: [],
		prefix: '/cp',
		assets: '',
		debug: false,
		allowedFiles: { file: [], image: [], video: [] },
	});
});
afterEach(() => {
	stop?.();
	document.body.innerHTML = '';
	delete window.Cosray;
});

it('keeps the complete catalog reachable and appends a non-common row through the form pipeline', () => {
	const form = setup();
	const change = vi.fn();
	const submit = vi.fn((event: Event) => event.preventDefault());
	form.addEventListener('change', change);
	form.addEventListener('submit', submit);
	expect(footer().querySelectorAll('[data-repeater-add]')).toHaveLength(6);
	const dialog = open();
	expect(document.activeElement).toBe(dialog.querySelector('input'));
	expect(choices(dialog).map((button) => button.dataset.blockChoice)).toEqual(
		types.map((type) => type.type),
	);
	search(dialog, 'iframe');
	choices(dialog)[0].click();
	const row = rows()[0];
	expect(row.querySelector<HTMLInputElement>('input[name$="[type]"]')?.value).toBe(types[7].type);
	expect(row.querySelector<HTMLInputElement>('[data-repeater-uid]')?.value).toMatch(/^\w{13}$/);
	expect(row.style.getPropertyValue('--span')).toBe('12');
	expect(row.contains(document.activeElement)).toBe(true);
	expect(change).toHaveBeenCalledOnce();
	expect(submit).not.toHaveBeenCalled();
	const text = row.querySelector<HTMLInputElement>('input[type="text"]')!;
	text.value = 'Catalog value';
	expect(text.form).toBe(form);
	expect(nest([...new FormData(form)].map(([name, value]) => [name, String(value)]))).toMatchObject(
		{
			content: {
				story: {
					value: {
						zxx: {
							0: {
								type: types[7].type,
								layout: { span: '12', rows: '1', indent: '0' },
								fields: { text: { value: { zxx: 'Catalog value' } } },
							},
						},
					},
				},
			},
		},
	);
});

it('inserts before the captured row after its position changes and starts fresh when reopened', () => {
	setup();
	quick();
	quick();
	const anchor = rows()[1];
	const dialog = open(anchor);
	anchor.querySelector<HTMLButtonElement>('[data-repeater-move="up"]')!.click();
	choices(dialog)[7].click();
	expect(rows()[1]).toBe(anchor);
	expect(rows()[0].querySelector<HTMLInputElement>('input[name$="[type]"]')?.value).toBe(
		types[7].type,
	);
	choices(open())[0].click();
	expect(rows()).toHaveLength(4);
	expect(rows()[3].querySelector<HTMLInputElement>('input[name$="[type]"]')?.value).toBe(
		types[0].type,
	);
});

it.each([
	'removed anchor',
	'moved anchor',
	'removed owner',
	'hidden owner',
	'inert owner',
	'changed locale',
	'removed template',
])('refuses stale selections with %s', (state) => {
	const form = setup(field() + field('other'));
	const first = owner();
	quick();
	const anchor = rows(first)[0];
	const dialog = open(anchor);
	const changed = vi.fn();
	form.addEventListener('change', changed);
	const second = form.querySelectorAll<HTMLElement>('[data-repeater]')[1];
	if (state === 'removed anchor') anchor.remove();
	if (state === 'moved anchor') second.querySelector('[data-repeater-list]')!.append(anchor);
	if (state === 'removed owner') first.remove();
	if (state === 'hidden owner') first.hidden = true;
	if (state === 'inert owner') first.setAttribute('inert', '');
	if (state === 'changed locale') selectContentLocale(form, 'de');
	if (state === 'removed template')
		first.querySelectorAll('template[data-repeater-template]')[7].remove();
	choices(dialog)[7].click();
	expect(changed).not.toHaveBeenCalled();
	expect(rows(second).length).toBe(state === 'moved anchor' ? 1 : 0);
	expect(document.querySelector('dialog[open]')).toBeNull();
});

it('isolates fields and asymmetric locale instances, including inactive fallback previews', () => {
	const form = setup(field('story', [types[1].type], 'asymmetric') + field('other'));
	const variants = [...form.querySelectorAll<HTMLElement>('[data-blocks-locale]')];
	const english = owner(variants.find((variant) => variant.dataset.locale === 'en')!);
	const german = owner(variants.find((variant) => variant.dataset.locale === 'de')!);
	quick(footer(english), 0);
	selectContentLocale(form, 'de');
	const localeControl = form.querySelector('[data-content-locale-control]')!;
	localeControl.dispatchEvent(new Event('change', { bubbles: true }));
	expect(english.closest<HTMLElement>('[data-blocks-locale]')?.inert).toBe(true);
	const changed = vi.fn();
	form.addEventListener('change', changed);
	const dialog = open(footer(german));
	choices(dialog)[7].click();
	expect(rows(english)).toHaveLength(1);
	expect(rows(german)).toHaveLength(1);
	expect(rows(german)[0].querySelector('input')?.name).toContain('[value][de][0]');
	expect(changed).toHaveBeenCalledOnce();
	const other = [...form.querySelectorAll<HTMLElement>('[data-repeater]')].at(-1)!;
	expect(rows(other)).toHaveLength(0);
});

it('cancels and searches without changing values, restores focus and resets on reopening', () => {
	const form = setup();
	const changed = vi.fn();
	form.addEventListener('change', changed);
	const initial = [...new FormData(form)];
	const dialog = open();
	const input = search(dialog, 'ÜBERSCHRIFT');
	expect(choices(dialog)).toHaveLength(1);
	expect(choices(dialog)[0].dataset.handle).toBe('heading');
	expect(document.activeElement).toBe(input);
	search(dialog, 'not a type');
	expect(choices(dialog)).toHaveLength(0);
	expect(dialog.querySelector('[role="status"]')?.textContent).toBe('No matching block types.');
	expect(key(input, 'Enter').defaultPrevented).toBe(true);
	expect(key(input, 'ArrowDown').defaultPrevented).toBe(true);
	cancel(dialog);
	expect(document.activeElement).toBe(footer().querySelector('button[popovertarget]'));
	expect([...new FormData(form)]).toEqual(initial);
	expect(changed).not.toHaveBeenCalled();
	const reopened = open();
	expect(reopened.querySelector('input')?.value).toBe('');
	expect(choices(reopened)).toHaveLength(8);
});

it.each([2, 4])('uses one roving stop and navigates the visible %s-column grid', (columns) => {
	setup();
	const dialog = open();
	const buttons = choices(dialog);
	buttons.forEach((button, index) =>
		vi
			.spyOn(button, 'getBoundingClientRect')
			.mockReturnValue(
				new DOMRect((index % columns) * 120, Math.floor(index / columns) * 100, 110, 90),
			),
	);
	key(dialog.querySelector('input')!, 'ArrowDown');
	expect(document.activeElement).toBe(buttons[0]);
	key(buttons[0], 'ArrowRight');
	key(buttons[1], 'ArrowDown');
	expect(document.activeElement).toBe(buttons[columns + 1]);
	key(buttons[columns + 1], 'ArrowUp');
	expect(document.activeElement).toBe(buttons[1]);
	key(buttons[1], 'End');
	expect(document.activeElement).toBe(buttons[7]);
	key(buttons[7], 'Home');
	expect(document.activeElement).toBe(buttons[0]);
	expect(buttons.filter((button) => button.tabIndex === 0)).toEqual([buttons[0]]);
	expect(key(buttons[0], 'Tab').defaultPrevented).toBe(false);
	search(dialog, 'text');
	expect(choices(dialog)).toEqual(buttons.slice(0, 2));
	search(dialog, 'iframe');
	expect(buttons[7].tabIndex).toBe(0);
	search(dialog, '');
	expect(buttons[7].tabIndex).toBe(0);
});

it.each([0, 1, 4, 6])('keeps the %s-type catalog usable without a redundant modal', (count) => {
	setup(field('story', undefined, undefined, types.slice(0, count)));
	if (count === 0) expect(footer().textContent).toContain('No block types available.');
	else {
		quick(footer(), 0);
		expect(rows()).toHaveLength(1);
	}
	expect(footer().querySelector('[data-block-catalog-open]')).toBeNull();
});

it('offers a small explicit subset in declared order and every type in its catalog', () => {
	setup(field('story', [types[2].type, types[0].type], undefined, types.slice(0, 4)));
	expect(
		[...footer().querySelectorAll<HTMLElement>('[data-repeater-add]')].map(
			(choice) => choice.dataset.repeaterAdd,
		),
	).toEqual([types[2].type, types[0].type]);
	expect(choices(open()).map((choice) => choice.dataset.blockChoice)).toEqual(
		types.slice(0, 4).map((type) => type.type),
	);
});

it('cannot insert through a choice from a cancelled or already-used catalog', () => {
	setup();
	const dialog = open();
	const choice = choices(dialog)[7];
	cancel(dialog);
	choice.click();
	expect(rows()).toHaveLength(0);
	const current = choices(open())[7];
	current.click();
	current.click();
	expect(rows()).toHaveLength(1);
});

it('renders descriptor labels as text rather than executable catalog markup', () => {
	const label = '<img src=x onerror="alert(1)">';
	const custom = [...types.slice(0, 7), { ...types[7], label }];
	setup(field('story', undefined, undefined, custom));
	const dialog = open();
	const choice = choices(dialog)[7];
	expect(choice.textContent).toContain(label);
	expect(choice.querySelector('img')).toBeNull();
	search(dialog, '<img');
	expect(choices(dialog)).toEqual([choice]);
	choice.click();
	expect(rows()).toHaveLength(1);
});

it('cleans up on owner replacement and behavior disposal', async () => {
	setup();
	open();
	owner().remove();
	await vi.waitFor(() => expect(document.querySelector('dialog[open]')).toBeNull());
	stop();
});
