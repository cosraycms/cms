import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '../../../src/elements/richtext.js';
import { installBridge } from '../../../src/lib/bridge-standalone';
import type { UploadResult } from '../../../src/lib/bridge';
import { install as installMenus } from '../../../src/lib/action-menu';

vi.mock('$lib/locale', () => ({ __: (id: string) => id }));

const tick = () => Promise.resolve();
let stopMenus: () => void;

beforeEach(() => {
	stopMenus = installMenus();
	installBridge({
		locale: 'en',
		defaultLocale: 'en',
		locales: [{ id: 'en', title: 'English' }],
		customLocales: [],
		prefix: '/cp',
		assets: '/assets',
		debug: false,
		allowedFiles: { file: [], image: ['png'], video: [] },
	});
	// The dialogs load the library picker through htmx.
	vi.stubGlobal('htmx', { ajax: vi.fn().mockResolvedValue(undefined) });
});

afterEach(async () => {
	stopMenus();
	vi.restoreAllMocks();
	document.querySelector<HTMLButtonElement>('[data-dialog-close]')?.click();
	document.body.replaceChildren();
	delete window.Cosray;
	vi.unstubAllGlobals();
});

async function editor(
	props: { classes?: Record<string, string>; styles?: Record<string, string> } = {},
) {
	const host = document.createElement('form');
	document.body.append(host);
	const notify = vi.fn();
	const element = document.createElement('cosray-richtext');
	Object.assign(element, {
		format: 'cosray-richtext',
		field: {
			name: 'body',
			tools: ['link', 'image'],
			richtextClasses: props.classes ?? {},
			richtextStyles: props.styles ?? {},
		},
		value: {
			zxx: {
				type: 'doc',
				content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Hello world' }] }],
			},
		},
	});
	element.addEventListener('cosray-change', () => notify());
	host.append(element);
	await tick();
	for (const trigger of host.querySelectorAll('[popovertarget]')) {
		vi.spyOn(trigger, 'getBoundingClientRect').mockReturnValue(new DOMRect(100, 100, 24, 24));
	}
	const content = host.querySelector<HTMLElement>('[contenteditable="true"]')!;
	content.focus();
	const text = content.querySelector('p')!.firstChild!;
	const range = document.createRange();
	range.setStart(text, 0);
	range.setEnd(text, 5);
	const selection = window.getSelection()!;
	selection.removeAllRanges();
	selection.addRange(range);
	document.dispatchEvent(new Event('selectionchange'));
	await tick();
	return { host, content, notify };
}

async function action(label: string) {
	const button = Array.from(document.querySelectorAll('button')).find(
		(button) =>
			button.checkVisibility() &&
			(button.getAttribute('aria-label') === label || button.textContent?.trim() === label),
	)!;
	expect(button).toBeDefined();
	button.focus();
	button.click();
	await tick();
}

describe('richtext action menus', () => {
	it('applies a paragraph class to the selected paragraph and returns focus to the document', async () => {
		const { content, notify } = await editor({ classes: { lead: 'Lead' } });
		await action('richtext:paragraph');
		await action('Lead');
		expect(content.querySelector('p')?.className).toBe('lead');
		expect(content.textContent).toBe('Hello world');
		expect(document.activeElement).toBe(content);
		expect(notify).toHaveBeenCalledOnce();
	});

	it('applies and clears a text style on the original selection without submitting the editor', async () => {
		const { host, content } = await editor({ styles: { accent: 'Accent' } });
		const submit = vi.fn((event: Event) => event.preventDefault());
		host.addEventListener('submit', submit);
		await action('richtext:text-style');
		await action('Accent');
		expect(content.querySelector('span.accent')?.textContent).toBe('Hello');
		expect(document.activeElement).toBe(content);
		await action('richtext:text-style');
		await action('richtext:remove-style');
		expect(content.querySelector('span.accent')).toBeNull();
		expect(content.textContent).toBe('Hello world');
		expect(submit).not.toHaveBeenCalled();
	});
});

describe('richtext dialogs', () => {
	it.each(['toolbar', 'overflow'])(
		'applies a link from the %s to the original selection and returns focus to the editor',
		async (entry) => {
			const { host, content, notify } = await editor();
			if (entry === 'overflow') {
				const trigger = host.querySelector<HTMLButtonElement>('[popovertarget]')!;
				vi.spyOn(trigger, 'getBoundingClientRect').mockReturnValue(new DOMRect(100, 100, 24, 24));
				trigger.focus();
				trigger.dispatchEvent(
					new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true, cancelable: true }),
				);
				await tick();
				expect(document.activeElement?.textContent?.trim()).toBe('richtext:add-page-link');
			}
			await action('richtext:add-page-link');
			const input = document.querySelector<HTMLInputElement>('dialog input[type="text"]')!;
			expect(document.activeElement).toBe(input);
			input.value = 'https://example.test/';
			input.dispatchEvent(new Event('input', { bubbles: true }));
			await tick();
			await action('link:add');
			expect(content.querySelector('a')?.textContent).toBe('Hello');
			expect(content.querySelector('a')?.getAttribute('href')).toBe('https://example.test/');
			expect(content.textContent).toBe('Hello world');
			expect(document.activeElement).toBe(content);
			expect(notify).toHaveBeenCalledOnce();
		},
	);

	it('cancels a link without editing the selected text', async () => {
		const { content, notify } = await editor();
		await action('richtext:add-page-link');
		document.querySelector('dialog')!.dispatchEvent(new Event('cancel', { cancelable: true }));
		await tick();
		expect(content.textContent).toBe('Hello world');
		expect(notify).not.toHaveBeenCalled();
	});

	it('does not apply an image when its upload finishes after dismissal', async () => {
		const { notify } = await editor();
		const pending = Promise.withResolvers<UploadResult>();
		vi.spyOn(window.Cosray!, 'upload').mockReturnValue(pending.promise);
		await action('image:insert');
		const input = document.querySelector<HTMLInputElement>('dialog input[type="file"]')!;
		Object.defineProperty(input, 'files', { value: [new File(['image'], 'image.png')] });
		input.dispatchEvent(new Event('change', { bubbles: true }));
		await tick();
		document.querySelector('dialog')!.dispatchEvent(new Event('cancel', { cancelable: true }));
		await tick();
		pending.resolve({ ok: true, uid: 'uploaded', filename: 'image.png', url: '/image.png' });
		await tick();
		expect(notify).not.toHaveBeenCalled();
	});

	it('inserts a library image picked in the dialog', async () => {
		const { content, notify } = await editor();
		pickerWith({
			uid: 'pic',
			filename: 'pic.png',
			url: '/assets/pic.png',
			thumbUrl: '/cache/pic-thumb.png',
			kind: 'image',
		});

		await action('image:insert');
		document.querySelector<HTMLElement>('dialog [data-pick]')!.click();
		document.querySelector<HTMLButtonElement>('dialog .modal-footer .primary')!.click();
		await tick();

		const image = content.querySelector<HTMLImageElement>('img[data-uid="pic"]');
		expect(image?.getAttribute('src')).toBe('/cache/pic-thumb.png');
		expect(notify).toHaveBeenCalledOnce();
	});

	it('links the selection to an asset picked on the files tab', async () => {
		const { content, notify } = await editor();
		pickerWith({
			uid: 'doc',
			filename: 'doc.pdf',
			url: '/assets/doc.pdf',
			thumbUrl: '/assets/doc.pdf',
			kind: 'file',
		});

		await action('richtext:add-page-link');
		expect(
			document.querySelector<HTMLButtonElement>('dialog .modal-footer .primary')!.disabled,
		).toBe(true);
		[...document.querySelectorAll<HTMLElement>('dialog [role="tab"]')]
			.find((tab) => tab.textContent?.includes('media:files-documents'))!
			.click();
		await tick();
		document.querySelector<HTMLElement>('dialog [data-pick]')!.click();
		await action('link:add');

		expect(content.querySelector('a')?.getAttribute('data-asset')).toBe('doc');
		expect(content.querySelector('a')?.textContent).toBe('Hello');
		expect(notify).toHaveBeenCalledOnce();
	});
});

// The picker the dialogs embed, as the server would render it.
function pickerWith(item: Record<string, string>): void {
	vi.stubGlobal('htmx', {
		ajax: vi.fn(async (_verb: string, _path: string, { target }: { target: Element }) => {
			target.innerHTML = `<button type="button" class="cms-asset-tile" data-pick='${JSON.stringify(item)}'></button>`;
		}),
	});
}
