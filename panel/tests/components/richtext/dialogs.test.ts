import { mount, tick, unmount } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RichTextEditor from '../../../src/components/richtext/RichTextEditor.svelte';
import { installBridge } from '../../../src/lib/bridge-standalone';
import type { UploadResult } from '../../../src/lib/bridge';

vi.mock('$lib/locale', () => ({ __: (id: string) => id }));

let app: ReturnType<typeof mount>;

beforeEach(() => {
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
});

afterEach(async () => {
	document.querySelector<HTMLButtonElement>('[data-dialog-close]')?.click();
	if (app) await unmount(app);
	document.body.replaceChildren();
	delete window.Cosray;
	vi.unstubAllGlobals();
});

async function editor() {
	const host = document.createElement('div');
	document.body.append(host);
	const notify = vi.fn();
	app = mount(RichTextEditor, {
		target: host,
		props: {
			name: 'body',
			tools: ['link', 'image'],
			notify,
			value: {
				type: 'doc',
				content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Hello world' }] }],
			},
		},
	});
	await tick();
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
		(button) => button.getAttribute('aria-label') === label || button.textContent?.trim() === label,
	)!;
	expect(button).toBeDefined();
	button.focus();
	button.click();
	await tick();
}

describe('richtext dialogs', () => {
	it('applies a link to the original selection and returns focus to the editor', async () => {
		const { content, notify } = await editor();
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
	});

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
		vi.stubGlobal(
			'fetch',
			vi
				.fn()
				.mockResolvedValue({ ok: true, json: async () => ({ items: [], page: 1, more: false }) }),
		);
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
});
