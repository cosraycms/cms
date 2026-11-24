import { afterEach, describe, expect, it, vi } from 'vitest';

import type { RichtextEnvelope } from '../../src/elements/richtext/format.js';
import type { HostPayload } from '../../src/lib/host';
import type { LocaleMap, RichtextDoc } from '../../src/types/data';
import { place } from '../../src/elements/richtext.js';

vi.mock('../../src/lib/locale.js', () => ({ __: (id: string) => id }));

// The element updates synchronously; a microtask lets pending work land.
const tick = () => Promise.resolve();

type RichtextElement = HTMLElement & HostPayload & { locale: string };

function doc(text: string): RichtextDoc {
	return {
		type: 'doc',
		content: [
			{ type: 'paragraph', attrs: { class: 'default' }, content: [{ type: 'text', text }] },
		],
	};
}

async function richtext(
	value: LocaleMap<RichtextDoc | string | null> | null,
	format = 'cosray-richtext',
) {
	const element = document.createElement('cosray-richtext') as RichtextElement;
	Object.assign(element, {
		value,
		format,
		field: { name: 'body', translate: true, tools: ['source'] },
		locale: 'de',
		locales: {
			default: 'en',
			all: [
				{ id: 'en', title: 'English' },
				{ id: 'de', title: 'Deutsch', fallback: 'en' },
			],
		},
	});
	const changes = vi.fn<(detail: RichtextEnvelope) => void>();
	element.addEventListener('cosray-change', (event) => {
		changes(JSON.parse(JSON.stringify((event as CustomEvent).detail)));
	});
	document.body.append(element);
	await tick();

	return { element, changes };
}

async function editSource(element: HTMLElement, html: string): Promise<void> {
	const button = Array.from(element.querySelectorAll('button')).find(
		(button) => button.textContent?.trim() === 'richtext:show-source',
	);
	expect(button).toBeDefined();
	button!.click();
	await tick();
	const input = element.querySelector('textarea')!;
	input.value = html;
	// A paste, drop or composition fires input without a key.
	input.dispatchEvent(new Event('input', { bubbles: true }));
	await tick();
}

afterEach(async () => {
	document.body.replaceChildren();
	await tick();
});

describe('source view', () => {
	it('marks its text invalid by the editor messages, also after a language switch', async () => {
		const { element } = await richtext({ en: doc('Hello') });
		const text = () => element.querySelector('.cms-richtext-content > .ProseMirror');
		(element as unknown as { errorIds: string[] }).errorIds = ['cms-error-1'];

		expect(text()?.getAttribute('aria-invalid')).toBe('true');
		expect(text()?.getAttribute('aria-describedby')).toBe('cms-error-1');

		element.locale = 'en';

		expect(text()?.getAttribute('aria-describedby')).toBe('cms-error-1');

		(element as unknown as { errorIds: string[] }).errorIds = [];

		expect(text()?.hasAttribute('aria-invalid')).toBe(false);
		expect(text()?.hasAttribute('aria-describedby')).toBe(false);
	});

	it('names its document as a text box of several lines', async () => {
		const element = document.createElement('cosray-richtext') as RichtextElement;
		Object.assign(element, {
			value: null,
			field: { name: 'body', label: 'Body' },
			locale: 'zxx',
			locales: { default: 'en', all: [{ id: 'en', title: 'English' }] },
		});
		document.body.append(element);
		await tick();
		const text = element.querySelector('.cms-richtext-content > .ProseMirror');

		expect(text?.getAttribute('role')).toBe('textbox');
		expect(text?.getAttribute('aria-multiline')).toBe('true');
		expect(text?.getAttribute('aria-label')).toBe('Body');
	});

	it('returns to the content through a button that never submits the form', async () => {
		const { element } = await richtext({ en: doc('Hello'), de: null });
		await editSource(element, '<p>Hello</p>');
		const source = element.querySelector('textarea')!;
		const back = Array.from(element.querySelectorAll('button')).find(
			(button) => button.textContent?.trim() === 'richtext:show-content',
		);

		expect(source.closest('[hidden]')).toBeNull();
		// jsdom runs no implicit submission, so the type is the check.
		expect(back?.type).toBe('button');

		back!.click();
		await tick();

		expect(source.closest('[hidden]')).not.toBeNull();
	});

	it('reports nothing for keys that leave the source unchanged', async () => {
		const { element, changes } = await richtext({ en: doc('Hello'), de: null });
		await editSource(element, '<p>Hello there</p>');
		changes.mockClear();
		const source = element.querySelector('textarea')!;
		source.dispatchEvent(new KeyboardEvent('keyup', { key: 'ArrowLeft', bubbles: true }));
		await tick();

		expect(changes).not.toHaveBeenCalled();
	});
});

describe('richtext fallback previews', () => {
	it('keeps the target empty when focusing and switching away from shared content', async () => {
		const { element, changes } = await richtext({ zxx: doc('Shared text'), en: null, de: null });

		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain('Shared text');
		const editor = element.querySelector<HTMLElement>('[contenteditable="true"]')!;
		expect(editor.textContent).toBe('');
		editor.focus();
		await tick();
		expect(element.querySelector('.cms-richtext-fallback')).toBeNull();
		expect(editor.textContent).toBe('');
		editor.blur();
		await tick();
		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain('Shared text');

		element.locale = 'en';
		await tick();
		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain('Shared text');
		expect(element.querySelector('[contenteditable="true"]')?.textContent).toBe('');
		expect(changes).not.toHaveBeenCalled();
		expect(element.value).toEqual({ zxx: doc('Shared text'), en: null, de: null });
	});

	it('retains shared content in the submitted map after editing a translation', async () => {
		const { element, changes } = await richtext({ zxx: doc('Shared text'), en: null, de: null });
		element.locale = 'en';
		await tick();
		await editSource(element, '<p>Edited English</p>');

		expect(changes).toHaveBeenLastCalledWith({
			format: 'cosray-richtext',
			version: 1,
			value: { zxx: doc('Shared text'), en: doc('Edited English'), de: null },
		});
		element.locale = 'de';
		await tick();
		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain(
			'Edited English',
		);
		expect(element.querySelector('[contenteditable="true"]')?.textContent).toBe('');

		element.locale = 'en';
		await tick();
		await editSource(element, '<p></p>');
		element.locale = 'de';
		await tick();
		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain('Shared text');
		expect(changes).toHaveBeenLastCalledWith({
			format: 'cosray-richtext',
			version: 1,
			value: {
				zxx: doc('Shared text'),
				en: { type: 'doc', content: [{ type: 'paragraph', attrs: { class: 'default' } }] },
				de: null,
			},
		});
	});

	it('converts shared legacy HTML without filling missing translations', async () => {
		const { element, changes } = await richtext(
			{ zxx: '<p>Shared text</p>', en: '', de: '' },
			'html',
		);

		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain('Shared text');
		expect(element.querySelector('[contenteditable="true"]')?.textContent).toBe('');
		expect(changes).toHaveBeenLastCalledWith({
			format: 'cosray-richtext',
			version: 1,
			value: { zxx: doc('Shared text'), en: null, de: null },
		});
	});
});

describe('the menu follows the tools', () => {
	async function field(tools: string[], extra: Record<string, unknown> = {}) {
		const element = document.createElement('cosray-richtext') as RichtextElement;
		Object.assign(element, {
			value: { zxx: doc('Some text') },
			format: 'cosray-richtext',
			field: { name: 'text', tools, ...extra },
			locale: 'zxx',
		});
		const changes = vi.fn<(detail: RichtextEnvelope) => void>();
		element.addEventListener('cosray-change', (event) => {
			changes(JSON.parse(JSON.stringify((event as CustomEvent).detail)));
		});
		document.body.append(element);
		await tick();

		return { element, changes };
	}

	// The bubble's own buttons and menu triggers, without the menus' entries.
	function bubbled(element: HTMLElement): (string | null)[] {
		const bubble = element.querySelector('.cms-richtext-bubble')!;

		return Array.from(
			bubble.querySelectorAll(':scope > button, :scope > .cms-richtext-dropdown > button'),
		).map((button) => button.getAttribute('aria-label'));
	}

	function press(element: HTMLElement, key: string): void {
		element
			.querySelector<HTMLElement>('[contenteditable="true"]')!
			.dispatchEvent(
				new KeyboardEvent('keydown', { key, shiftKey: true, ctrlKey: true, bubbles: true }),
			);
	}

	it('gives a field of marks only a bubble instead of the toolbar', async () => {
		const { element } = await field(['undo', 'bold', 'italic', 'strike', 'link', 'clear']);

		expect(element.querySelector('.cms-richtext-toolbar')).toBeNull();
		expect(bubbled(element)).toEqual([
			'richtext:bold',
			'richtext:italic',
			'richtext:strikethrough',
			'richtext:add-page-link',
			'richtext:remove-formats',
		]);
	});

	it('offers the text styles in the bubble', async () => {
		const { element } = await field(['bold'], { richtextStyles: { lead: 'Lead' } });

		expect(bubbled(element)).toEqual(['richtext:text-style', 'richtext:bold']);
	});

	it('gives a field that changes the structure a toolbar, inside a block too', async () => {
		const { element } = await field(['bold', 'bullet-list'], { presentation: 'block' });

		expect(element.querySelector('.cms-richtext-toolbar')).not.toBeNull();
		expect(element.querySelector('.cms-richtext-bubble')).toBeNull();
	});

	it('turns a paragraph into a heading without a selection', async () => {
		const { element, changes } = await field(['h2', 'bullet-list']);

		element
			.querySelector<HTMLButtonElement>(
				'.cms-richtext-toolbar-btns [aria-label="richtext:heading-2"]',
			)!
			.click();
		await tick();

		expect(changes).toHaveBeenLastCalledWith(
			expect.objectContaining({
				value: {
					zxx: expect.objectContaining({
						content: [expect.objectContaining({ type: 'heading', attrs: { level: 2 } })],
					}),
				},
			}),
		);
	});

	it('says whether a toggle tool is on', async () => {
		const { element } = await field(['h2', 'bullet-list', 'undo']);
		const button = (label: string) =>
			element.querySelector<HTMLButtonElement>(
				`.cms-richtext-toolbar-btns [aria-label="richtext:${label}"]`,
			)!;

		expect(button('heading-2').getAttribute('aria-pressed')).toBe('false');
		button('heading-2').click();
		await tick();

		expect(button('heading-2').getAttribute('aria-pressed')).toBe('true');
		expect(button('undo').hasAttribute('aria-pressed')).toBe(false);
	});

	it('toggles a list from the keyboard', async () => {
		const { element, changes } = await field(['bullet-list']);

		press(element, '8');
		await tick();

		expect(changes).toHaveBeenLastCalledWith(
			expect.objectContaining({
				value: {
					zxx: expect.objectContaining({
						content: [expect.objectContaining({ type: 'bulletList' })],
					}),
				},
			}),
		);
	});

	it('keeps a field of marks only free of lists from the keyboard', async () => {
		const { element, changes } = await field(['bold']);

		press(element, '8');
		await tick();

		expect(changes).not.toHaveBeenCalled();
	});
});

describe('the toolbar from the keyboard', () => {
	async function field(tools: string[]) {
		const element = document.createElement('cosray-richtext') as RichtextElement;
		Object.assign(element, {
			value: { zxx: doc('Some text') },
			format: 'cosray-richtext',
			field: { name: 'text', tools },
			locale: 'zxx',
		});
		document.body.append(element);
		await tick();

		return element;
	}

	const text = (element: HTMLElement) =>
		element.querySelector<HTMLElement>('[contenteditable="true"]')!;

	function key(target: Element, init: KeyboardEventInit): void {
		target.dispatchEvent(
			new KeyboardEvent('keydown', { bubbles: true, cancelable: true, ...init }),
		);
	}

	// The toolbar's own controls, without the entries of its menus.
	function controls(bar: Element): HTMLButtonElement[] {
		return Array.from(bar.querySelectorAll<HTMLButtonElement>('button')).filter(
			(button) => !button.closest('[popover]'),
		);
	}

	it('is one tab stop that Alt+F10 reaches from the text', async () => {
		const element = await field(['bold', 'italic', 'h2']);
		const bar = element.querySelector('.cms-richtext-toolbar')!;
		const stops = controls(bar).filter((button) => button.tabIndex === 0);

		expect(bar.getAttribute('role')).toBe('toolbar');
		expect(stops).toHaveLength(1);

		text(element).focus();
		key(text(element), { key: 'F10', altKey: true });

		expect(document.activeElement).toBe(stops[0]);
	});

	it('moves with the arrow keys, Home and End, and returns to the text on Escape', async () => {
		const element = await field(['bold', 'italic', 'h2']);
		const shown = controls(element.querySelector('.cms-richtext-toolbar')!);

		shown[0].focus();
		key(shown[0], { key: 'ArrowRight' });
		expect(document.activeElement).toBe(shown[1]);
		expect(shown[1].tabIndex).toBe(0);
		expect(shown[0].tabIndex).toBe(-1);

		key(shown[1], { key: 'End' });
		expect(document.activeElement).toBe(shown[shown.length - 1]);

		key(shown[shown.length - 1], { key: 'ArrowRight' });
		expect(document.activeElement).toBe(shown[0]);

		key(shown[0], { key: 'Escape' });
		expect(document.activeElement).toBe(text(element));
	});

	it('leaves the bubble alone while nothing is selected', async () => {
		const element = await field(['bold', 'italic']);

		text(element).focus();
		key(text(element), { key: 'F10', altKey: true });

		expect(element.querySelector('.cms-richtext-bubble')!.getAttribute('role')).toBe('toolbar');
		expect(document.activeElement).toBe(text(element));
	});
});

describe('a RichLine', () => {
	async function line(value: RichtextDoc | null) {
		const element = document.createElement('cosray-richtext') as RichtextElement;
		Object.assign(element, {
			value: { zxx: value },
			format: 'cosray-richtext',
			field: { name: 'subtitle', line: true, tools: ['bold', 'italic', 'link'] },
			locale: 'zxx',
		});
		const changes = vi.fn<(detail: RichtextEnvelope) => void>();
		element.addEventListener('cosray-change', (event) => {
			changes(JSON.parse(JSON.stringify((event as CustomEvent).detail)));
		});
		document.body.append(element);
		await tick();

		return { element, changes };
	}

	it('formats through a bubble and takes no second paragraph', async () => {
		const { element, changes } = await line(doc('One line'));
		const content = element.querySelector<HTMLElement>('[contenteditable="true"]')!;

		content.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
		content.dispatchEvent(
			new KeyboardEvent('keydown', { key: 'Enter', shiftKey: true, bubbles: true }),
		);
		await tick();

		expect(element.querySelector('.cms-richtext-toolbar')).toBeNull();
		expect(element.querySelector('.cms-richtext-bubble')).not.toBeNull();
		expect(changes).not.toHaveBeenCalled();
		expect(content.querySelectorAll('p')).toHaveLength(1);
	});

	it('edits a stored document of several paragraphs as one line', async () => {
		const { element } = await line({
			type: 'doc',
			content: [
				{ type: 'paragraph', content: [{ type: 'text', text: 'First' }] },
				{ type: 'paragraph', content: [{ type: 'text', text: 'second' }] },
			],
		});
		const content = element.querySelector<HTMLElement>('[contenteditable="true"]')!;

		expect(content.querySelectorAll('p')).toHaveLength(1);
		expect(content.textContent).toBe('First second');
	});
});

describe('the docked toolbar of a block', () => {
	function rect(left: number, top: number, width: number, height: number): DOMRect {
		return { left, top, width, height, right: left + width, bottom: top + height } as DOMRect;
	}

	function at(element: Element, box: DOMRect): void {
		element.getBoundingClientRect = () => box;
	}

	function block(): { root: HTMLElement; toolbar: HTMLElement; scroller: HTMLElement } {
		document.body.innerHTML = `<div class="scroller" style="overflow-y: auto; padding-top: 32px">
			<div class="cms-blocks-editor-grid">
				<div class="cms-block">
					<div class="cms-block-chrome"></div>
					<div class="cms-richtext is-docked"><div class="cms-richtext-toolbar"></div></div>
				</div>
			</div>
		</div>`;
		at(document.querySelector('.cms-blocks-editor-grid')!, rect(100, 0, 800, 600));

		return {
			root: document.querySelector<HTMLElement>('.cms-richtext')!,
			toolbar: document.querySelector<HTMLElement>('.cms-richtext-toolbar')!,
			scroller: document.querySelector<HTMLElement>('.scroller')!,
		};
	}

	it('stays inside the field and clear of the block’s chrome', () => {
		const { root, toolbar } = block();
		at(root, rect(700, 200, 300, 100));
		at(toolbar, rect(690, 160, 300, 36));
		at(document.querySelector('.cms-block-chrome')!, rect(860, 180, 100, 28));

		place(root, toolbar);

		expect(root.style.getPropertyValue('--dock-shift')).toBe('-90px');
		// Shifted to 600–900, the toolbar still meets the chrome at 860.
		expect(root.style.getPropertyValue('--dock-lift')).toBe('20px');
	});

	it('lines up with the block’s chrome beside it', () => {
		const { root, toolbar } = block();
		at(root, rect(100, 200, 300, 100));
		at(toolbar, rect(100, 168, 200, 28));
		at(document.querySelector('.cms-block-chrome')!, rect(700, 165, 100, 28));

		place(root, toolbar);

		// Both bottoms at 193.
		expect(root.style.getPropertyValue('--dock-lift')).toBe('3px');
	});

	it('sticks at the scroller’s top edge, not below its padding', () => {
		const { root, toolbar } = block();
		at(root, rect(100, 200, 300, 100));
		at(toolbar, rect(100, 160, 200, 36));

		place(root, toolbar);

		// 40px from the toolbar's top to the text, 4px off the edge, less the padding.
		expect(root.style.getPropertyValue('--dock-top')).toBe('12px');
	});
});

describe('richtext without stored data', () => {
	// A freshly stamped repeater row renders no value and no envelope.
	it('mounts and submits an empty envelope', async () => {
		const { element, changes } = await richtext(null, '');

		expect(element.querySelector('[contenteditable="true"]')).not.toBeNull();
		expect(changes).toHaveBeenLastCalledWith({
			format: 'cosray-richtext',
			version: 1,
			value: { en: null, de: null },
		});
	});
});
