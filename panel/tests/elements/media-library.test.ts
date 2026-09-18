import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { installBridge } from '../../src/lib/bridge-standalone';
import '../../src/elements/media/LibraryElement.svelte';

vi.mock('$lib/locale', () => ({
	__: (id: string, args?: { count?: number }) =>
		args?.count === undefined ? id : `${id}: ${args.count}`,
}));

const asset = {
	uid: 'guide',
	filename: 'guide.pdf',
	url: '/assets/guide.pdf',
	thumbUrl: '/assets/guide.pdf',
	kind: 'file',
};

const fetcher = vi.fn<typeof fetch>();

beforeEach(() => {
	history.replaceState({}, '', '/cp/media');
	installBridge({
		locale: 'en',
		defaultLocale: 'en',
		locales: [{ id: 'en', title: 'English' }],
		customLocales: [],
		prefix: '/cp',
		assets: '/assets',
		debug: false,
		allowedFiles: { file: ['pdf'], image: ['png'], video: ['mp4'] },
	});
	fetcher.mockReset();
	fetcher.mockImplementation(async (url) => {
		const query = new URL(String(url), location.href).searchParams.get('q');
		return new Response(
			JSON.stringify({
				ok: true,
				assets: query ? [asset] : [],
				page: 1,
				more: false,
				total: query ? 1 : 0,
				counts: {},
			}),
		);
	});
	vi.stubGlobal('fetch', fetcher);
});

afterEach(async () => {
	document.body.replaceChildren();
	await tick();
	vi.unstubAllGlobals();
	delete window.Cosray;
});

async function library() {
	document.body.innerHTML = `
		<div class="page cms-media" data-content-locale-scope data-content-locale="en">
			<header class="head">
				<h1>Media</h1>
				<span class="cms-count" data-media-count hidden></span>
				<div class="actions" data-media-toolbar></div>
			</header>
			<section class="body"><cosray-media-library></cosray-media-library></section>
		</div>`;
	await vi.waitFor(() => expect(document.querySelector('input[type="search"]')).not.toBeNull());
	await vi.waitFor(() =>
		expect(document.querySelector('header')?.textContent).toContain('media:file-count: 0'),
	);
	return document.querySelector('cosray-media-library')!;
}

describe('media page controls', () => {
	it('searches from the toolbar and keeps results, count and URL state in sync', async () => {
		const element = await library();
		const search = element.querySelector<HTMLInputElement>('.toolbar input[type="search"]')!;
		search.value = 'guide';
		search.dispatchEvent(new Event('input', { bubbles: true }));
		await tick();
		expect(new URL(location.href).searchParams.has('q')).toBe(false);

		search.form!.requestSubmit();
		await vi.waitFor(() => expect(element.textContent).toContain('guide.pdf'));
		expect(new URL(location.href).searchParams.get('q')).toBe('guide');
		expect(document.querySelector('header')?.textContent).toContain('media:file-count: 1');
		expect(String(fetcher.mock.lastCall?.[0])).toContain('q=guide');

		search.value = '';
		search.dispatchEvent(new Event('input', { bubbles: true }));
		await vi.waitFor(() => expect(element.textContent).toContain('media:no-files'));
		expect(new URL(location.href).searchParams.has('q')).toBe(false);
		expect(document.querySelector('header')?.textContent).toContain('media:file-count: 0');
	});

	it('reconnects the upload controls with the library', async () => {
		const element = await library();
		element.remove();
		await tick();
		expect(document.querySelector('[data-media-toolbar]')?.childElementCount).toBe(0);
		expect(document.querySelector('[data-media-count]')?.childElementCount).toBe(0);

		await library();
		const picker = document.querySelector<HTMLInputElement>('header input[type="file"]')!;
		const open = vi.spyOn(picker, 'click').mockImplementation(() => {});
		const button = Array.from(document.querySelectorAll<HTMLButtonElement>('header button')).find(
			(button) => button.textContent?.trim() === 'common:upload',
		)!;
		button.click();
		expect(open).toHaveBeenCalledOnce();

		fetcher.mockResolvedValueOnce(
			new Response(JSON.stringify({ ok: false, error: 'Upload rejected' })),
		);
		const file = new File(['content'], 'guide.pdf', { type: 'application/pdf' });
		Object.defineProperty(picker, 'files', { value: [file] });
		picker.dispatchEvent(new Event('change', { bubbles: true }));

		await vi.waitFor(() => expect(document.body.textContent).toContain('Upload rejected'));
		const [url, request] = fetcher.mock.lastCall!;
		expect(url).toBe('/cp/media/file');
		expect(request?.method).toBe('POST');
		expect((request?.body as FormData).get('file')).toBe(file);
		expect(button.disabled).toBe(false);
	});
});
