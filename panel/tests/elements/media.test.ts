import Sortable, { type SortableEvent } from 'sortablejs';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { BridgeSystem, UploadResult } from '../../src/lib/bridge';
import type { HostPayload } from '../../src/lib/host';
import type { FileItem, LocaleMap } from '../../src/types/data';
import { installBridge } from '../../src/lib/bridge-standalone';
import '../../src/elements/media/FileElement.svelte';
import '../../src/elements/media/ImageElement.svelte';

vi.mock('$lib/locale', () => ({ __: (id: string) => id }));

const system: BridgeSystem = {
	locale: 'en',
	defaultLocale: 'en',
	locales: [
		{ id: 'en', title: 'English' },
		{ id: 'de', title: 'Deutsch', fallback: 'en' },
	],
	customLocales: [],
	prefix: '/cp',
	assets: '/assets',
	debug: false,
	allowedFiles: { file: ['pdf'], image: ['png'], video: ['mp4'] },
};

type MediaElement = HTMLElement & HostPayload & { locale: string };

beforeEach(() => {
	installBridge(system);
});

afterEach(async () => {
	document.querySelector<HTMLButtonElement>('.cms-modal [data-dialog-close]')?.click();
	document.body.replaceChildren();
	await tick();
	delete window.Cosray;
});

async function media(tag: string, payload: HostPayload, locale = 'de') {
	const element = document.createElement(tag) as MediaElement;
	Object.assign(element, payload);
	element.locale = locale;
	element.locales = { default: system.defaultLocale, all: system.locales };
	const changes = vi.fn<(value: LocaleMap<FileItem[]>) => void>();
	element.addEventListener('cosray-change', (event) => {
		changes(JSON.parse(JSON.stringify((event as CustomEvent).detail.value)));
	});
	document.body.append(element);
	await tick();

	return { element, changes };
}

function file() {
	return media('cosray-file', {
		value: { zxx: [{ uid: 'guide', meta: { title: { en: 'English title' } } }] },
		field: { name: 'downloads', translate: true, translateMode: 'symmetric' },
		assets: {
			guide: {
				filename: 'guide.pdf',
				url: '/media/guide.pdf',
				kind: 'file',
				meta: { title: { de: 'Catalog title' } },
			},
		},
	});
}

async function edit(element: HTMLElement): Promise<HTMLInputElement> {
	element.querySelector<HTMLButtonElement>('.cms-file-action-edit')!.click();
	await tick();
	const input = document.querySelector<HTMLInputElement>('.cms-modal input[type="text"]');
	expect(input).not.toBeNull();

	return input!;
}

async function enter(input: HTMLInputElement, value: string): Promise<void> {
	input.value = value;
	input.dispatchEvent(new Event('input', { bubbles: true }));
	await tick();
}

function upload(element: HTMLElement): void {
	const picker = element.querySelector<HTMLInputElement>('input[type="file"]')!;
	Object.defineProperty(picker, 'files', {
		value: [new File(['content'], 'uploaded.pdf', { type: 'application/pdf' })],
	});
	picker.dispatchEvent(new Event('input', { bubbles: true }));
}

async function action(label: string): Promise<void> {
	const button = Array.from(document.querySelectorAll<HTMLButtonElement>('.cms-modal button')).find(
		(button) => button.textContent?.trim() === label,
	);
	expect(button).toBeDefined();
	button!.click();
	await tick();
}

describe('file metadata', () => {
	it('opens with an empty translation and discards cancelled edits', async () => {
		const { element, changes } = await file();
		const original = structuredClone(element.value);
		const input = await edit(element);

		expect(input.value).toBe('');
		expect(document.activeElement).toBe(input);
		input.blur();
		await tick();
		expect(input.placeholder).toBe('English title');
		input.focus();
		await enter(input, 'Discarded title');
		await action('common:cancel');

		expect(changes).not.toHaveBeenCalled();
		expect(element.value).toEqual(original);
		expect((await edit(element)).value).toBe('');
	});

	it('applies only the selected translation without changing catalog metadata', async () => {
		const { element, changes } = await file();
		const catalog = structuredClone(element.assets);

		await enter(await edit(element), 'German title');
		await action('common:apply');

		expect(changes).toHaveBeenCalledExactlyOnceWith({
			zxx: [{ uid: 'guide', meta: { title: { en: 'English title', de: 'German title' } } }],
		});
		expect(element.assets).toEqual(catalog);
		expect((await edit(element)).value).toBe('German title');
	});
});

describe('media uploads', () => {
	it.each([1, 4])(
		'keeps a pending upload in its starting locale with a limit of %i',
		async (max) => {
			const pending = Promise.withResolvers<UploadResult>();
			vi.spyOn(window.Cosray!, 'upload').mockReturnValueOnce(pending.promise);
			const german = { uid: 'german', meta: { title: { zxx: 'German title' } } };
			const english = max === 1 ? [] : [{ uid: 'english' }];
			const { element, changes } = await media(
				'cosray-file',
				{
					value: { en: english, de: [german] },
					field: {
						name: 'downloads',
						translate: true,
						translateMode: 'asymmetric',
						limit: { max },
					},
				},
				'en',
			);
			upload(element);

			element.locale = 'de';
			await tick();
			expect(changes).not.toHaveBeenCalled();
			pending.resolve({
				ok: true,
				uid: 'uploaded',
				filename: 'uploaded.pdf',
				url: '/media/uploaded.pdf',
			});

			await vi.waitFor(() => {
				expect(changes).toHaveBeenCalledExactlyOnceWith({
					en: [...english, { uid: 'uploaded' }],
					de: [german],
				});
			});
			expect(element.querySelector('.cms-file-name')?.textContent).toBe('german');

			element.locale = 'en';
			await tick();
			expect(element.textContent).toContain('uploaded.pdf');
		},
	);

	it('merges a late upload with changes made after returning to its locale', async () => {
		const first = Promise.withResolvers<UploadResult>();
		const second = Promise.withResolvers<UploadResult>();
		vi.spyOn(window.Cosray!, 'upload')
			.mockReturnValueOnce(first.promise)
			.mockReturnValueOnce(second.promise);
		const { element, changes } = await media(
			'cosray-file',
			{
				value: {},
				field: { name: 'downloads', translate: true, translateMode: 'asymmetric' },
			},
			'en',
		);
		upload(element);
		element.locale = 'de';
		await tick();
		element.locale = 'en';
		await tick();
		upload(element);
		second.resolve({ ok: true, uid: 'second' });
		await vi.waitFor(() => expect(changes).toHaveBeenCalledTimes(1));
		first.resolve({ ok: true, uid: 'first' });

		await vi.waitFor(() => {
			expect(changes).toHaveBeenLastCalledWith({ en: [{ uid: 'second' }, { uid: 'first' }] });
		});
	});
});

describe('gallery ordering', () => {
	it.each(['en', 'de'])('persists a drag after opening in %s and selecting de', async (locale) => {
		const first = { uid: 'first', meta: { title: { zxx: 'First image' } } };
		const second = { uid: 'second' };
		const { element, changes } = await media(
			'cosray-image',
			{
				value: { en: [], de: [first, second] },
				field: { name: 'gallery', translate: true, translateMode: 'asymmetric' },
			},
			locale,
		);
		element.locale = 'de';
		await tick();
		expect(changes).not.toHaveBeenCalled();

		const grid = element.querySelector<HTMLElement>('.cms-gallery .tiles')!;
		const sorter = Sortable.get(grid);
		expect(sorter).toBeDefined();

		// jsdom has no drag layout; Sortable moves the DOM before calling onUpdate.
		grid.append(grid.firstElementChild!);
		sorter!.option('onUpdate')!.call(sorter!, { oldIndex: 0, newIndex: 1 } as SortableEvent);
		await tick();

		expect(changes).toHaveBeenCalledExactlyOnceWith({ en: [], de: [second, first] });
	});
});
