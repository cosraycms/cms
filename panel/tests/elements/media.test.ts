import Sortable, { type SortableEvent } from 'sortablejs';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { BridgeSystem, UploadResult } from '../../src/lib/bridge';
import type { HostPayload } from '../../src/lib/host';
import type { FileItem, LocaleMap, Meta } from '../../src/types/data';
import { installBridge } from '../../src/lib/bridge-standalone';
import '../../src/elements/media/FileElement.svelte';
import '../../src/elements/media/ImageElement.svelte';
import '../../src/elements/media/VideoElement.svelte';

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
	const details = vi.fn<(detail: { value: LocaleMap<FileItem[]>; meta?: Meta }) => void>();
	element.addEventListener('cosray-change', (event) => {
		const detail = JSON.parse(JSON.stringify((event as CustomEvent).detail));

		changes(detail.value);
		details(detail);
	});
	document.body.append(element);
	await tick();

	return { element, changes, details };
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

async function enter(input: HTMLInputElement | HTMLTextAreaElement, value: string): Promise<void> {
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
	it('shows the catalog title as the placeholder of an item without its own', async () => {
		const { element } = await media('cosray-file', {
			value: { zxx: [{ uid: 'guide' }] },
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
		const input = await edit(element);

		input.blur();
		await tick();

		expect(input.placeholder).toBe('Catalog title');
	});

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

describe('per-use meta', () => {
	it('offers alt text and caption for an image and keeps its stored title', async () => {
		const { element, changes } = await media('cosray-image', {
			value: { zxx: [{ uid: 'cover', meta: { title: { zxx: 'Kept title' } } }] },
			field: { name: 'cover', limit: { min: 0, max: 1 } },
			assets: { cover: { filename: 'cover.jpg', url: '/media/cover.jpg', kind: 'image' } },
		});
		const form = element.querySelector('.cms-media-meta')!;
		const caption = form.querySelector<HTMLTextAreaElement>('textarea[id$="-caption"]');

		expect(form.querySelector('input[id$="-alt"]')).not.toBeNull();
		expect(caption).not.toBeNull();
		expect(form.querySelector('[id$="-title"]')).toBeNull();

		await enter(caption!, 'Under the image');
		expect(changes).toHaveBeenLastCalledWith({
			zxx: [
				{
					uid: 'cover',
					meta: { title: { zxx: 'Kept title' }, caption: { zxx: 'Under the image' } },
				},
			],
		});

		await enter(caption!, '');
		expect(changes).toHaveBeenLastCalledWith({
			zxx: [{ uid: 'cover', meta: { title: { zxx: 'Kept title' } } }],
		});
	});

	it('keeps the open video dialog on the language chosen meanwhile', async () => {
		const { element } = await media('cosray-video', {
			value: {
				zxx: [{ uid: 'clip', meta: { caption: { de: 'Ein Clip', en: 'A clip' } } }],
			},
			field: { name: 'clip', translate: true, limit: { min: 0, max: 1 } },
			assets: { clip: { filename: 'clip.mp4', url: '/media/clip.mp4', kind: 'video' } },
		});
		element.querySelector<HTMLButtonElement>('.cms-video-edit')!.click();
		await tick();
		const caption = document.querySelector<HTMLTextAreaElement>(
			'.cms-modal textarea[id$="-caption"]',
		)!;
		const mirror = document.querySelector<HTMLElement>('.cms-modal .cms-content-locales')!;

		expect(caption.value).toBe('Ein Clip');
		expect(mirror.querySelector('[aria-pressed="true"]')?.textContent?.trim()).toBe('Deutsch');

		const requests: string[] = [];
		document.addEventListener(
			'content-locale:select',
			(event) => requests.push((event as CustomEvent<{ locale: string }>).detail.locale),
			{ once: true },
		);
		mirror.querySelectorAll('button')[0].click();

		expect(requests).toEqual(['en']);

		element.locale = 'en';
		await tick();

		expect(caption.value).toBe('A clip');
		expect(mirror.querySelector('[aria-pressed="true"]')?.textContent?.trim()).toBe('English');
	});

	it('edits the caption of a video through its modal', async () => {
		const { element, changes } = await media('cosray-video', {
			value: { zxx: [{ uid: 'clip' }] },
			field: { name: 'clip', limit: { min: 0, max: 1 } },
			assets: { clip: { filename: 'clip.mp4', url: '/media/clip.mp4', kind: 'video' } },
		});
		element.querySelector<HTMLButtonElement>('.cms-video-edit')!.click();
		await tick();
		const caption = document.querySelector<HTMLTextAreaElement>(
			'.cms-modal textarea[id$="-caption"]',
		);

		expect(caption).not.toBeNull();
		expect(document.querySelector('.cms-modal [id$="-alt"], .cms-modal [id$="-title"]')).toBeNull();

		await enter(caption!, 'A short clip');
		await action('common:apply');

		expect(changes).toHaveBeenCalledExactlyOnceWith({
			zxx: [{ uid: 'clip', meta: { caption: { zxx: 'A short clip' } } }],
		});
	});
});

describe('block presentation', () => {
	const cover = { filename: 'cover.jpg', url: '/media/cover.jpg', kind: 'image' };

	async function figure(settings?: HTMLElement, item: FileItem = { uid: 'cover' }) {
		return media('cosray-image', {
			value: { zxx: [item] },
			field: { name: 'image', limit: { min: 0, max: 1 }, presentation: 'block' },
			assets: { cover },
			...(settings ? { settings } : {}),
		} as HostPayload);
	}

	it('renders an image as a figure with its per-use form below it', async () => {
		const { element } = await figure(undefined, {
			uid: 'cover',
			meta: { caption: { zxx: 'Under the image' } },
		});

		expect(element.querySelector('.cms-image-card')).toBeNull();
		expect(element.querySelector('.cms-image-figure img')?.getAttribute('src')).toBe(
			'/media/cover.jpg',
		);
		expect(element.querySelector('.cms-image-figure figcaption')?.textContent).toBe(
			'Under the image',
		);
		expect(
			element.querySelector('.cms-image-figure .cms-media-meta input[id$="-alt"]'),
		).not.toBeNull();
	});

	it("shows a neutral image's catalog texts in the site's default locale", async () => {
		const { element } = await media('cosray-image', {
			value: { zxx: [{ uid: 'cover' }] },
			field: { name: 'image', limit: { min: 0, max: 1 }, presentation: 'block' },
			assets: {
				cover: {
					...cover,
					meta: {
						caption: { en: 'From the catalog', de: 'Aus dem Katalog' },
						alt: { en: 'A kettle' },
					},
				},
			},
		});

		expect(element.querySelector('.cms-image-figure figcaption')?.textContent).toBe(
			'From the catalog',
		);
		expect(
			element.querySelector<HTMLInputElement>('.cms-media-meta input[id$="-alt"]')?.placeholder,
		).toBe('A kettle');
	});

	it('keeps the form presentation for a standalone field', async () => {
		const { element } = await media('cosray-image', {
			value: { zxx: [{ uid: 'cover' }] },
			field: { name: 'image', limit: { min: 0, max: 1 } },
			assets: { cover },
		});

		expect(element.querySelector('.cms-image-card')).not.toBeNull();
		expect(element.querySelector('.cms-image-figure')).toBeNull();
	});

	it('mounts the per-use form into the settings slot and edits through it', async () => {
		const slot = document.createElement('div');
		document.body.append(slot);
		const { element, changes } = await figure(slot);
		const alt = slot.querySelector<HTMLInputElement>('.cms-media-meta input[id$="-alt"]');

		expect(alt).not.toBeNull();
		expect(element.querySelector('.cms-media-meta')).toBeNull();

		await enter(alt!, 'A copper kettle');

		expect(changes).toHaveBeenLastCalledWith({
			zxx: [{ uid: 'cover', meta: { alt: { zxx: 'A copper kettle' } } }],
		});
	});

	it('mirrors the content language in the settings slot of a translated image', async () => {
		const slot = document.createElement('div');
		document.body.append(slot);
		const { element } = await media('cosray-image', {
			value: { zxx: [{ uid: 'cover' }] },
			field: { name: 'image', translate: true, limit: { min: 0, max: 1 }, presentation: 'block' },
			assets: { cover },
			settings: slot,
		} as HostPayload);
		const mirror = slot.querySelector<HTMLElement>('.cms-content-locales')!;
		const requests: string[] = [];
		document.addEventListener(
			'content-locale:select',
			(event) => requests.push((event as CustomEvent<{ locale: string }>).detail.locale),
			{ once: true },
		);

		expect(mirror.querySelector('[aria-pressed="true"]')?.textContent?.trim()).toBe('Deutsch');
		expect(element.querySelector('.cms-content-locales')).toBeNull();

		mirror.querySelectorAll('button')[0].click();

		expect(requests).toEqual(['en']);
	});

	it('replaces the slot form along with the image and clears it on remove', async () => {
		vi.spyOn(window.Cosray!, 'upload').mockResolvedValueOnce({
			ok: true,
			uid: 'fresh',
			filename: 'fresh.png',
			url: '/media/fresh.png',
		});
		const slot = document.createElement('div');
		document.body.append(slot);
		const { element, changes } = await figure(slot, {
			uid: 'cover',
			meta: { alt: { zxx: 'Old alt' } },
		});

		expect(slot.querySelector<HTMLInputElement>('input[id$="-alt"]')!.value).toBe('Old alt');

		upload(element);
		await vi.waitFor(() => {
			expect(element.querySelector('.cms-image-figure img')?.getAttribute('src')).toBe(
				'/media/fresh.png',
			);
		});
		const alt = slot.querySelector<HTMLInputElement>('input[id$="-alt"]');

		expect(slot.querySelectorAll('.cms-media-meta')).toHaveLength(1);
		expect(alt!.value).toBe('');

		await enter(alt!, 'New alt');
		expect(changes).toHaveBeenLastCalledWith({
			zxx: [{ uid: 'fresh', meta: { alt: { zxx: 'New alt' } } }],
		});

		element
			.querySelector<HTMLButtonElement>('.cms-image-figure .overlay button:last-child')!
			.click();
		await tick();

		expect(changes).toHaveBeenLastCalledWith({ zxx: [] });
		expect(slot.querySelector('.cms-media-meta')).toBeNull();
		expect(element.querySelector('.cms-image-figure .dropzone')).not.toBeNull();
	});

	it('renders a video as a player with its caption form in the slot', async () => {
		const slot = document.createElement('div');
		document.body.append(slot);
		const { element } = await media('cosray-video', {
			value: { zxx: [{ uid: 'clip' }] },
			field: { name: 'video', limit: { min: 0, max: 1 }, presentation: 'block' },
			assets: { clip: { filename: 'clip.mp4', url: '/media/clip.mp4', kind: 'video' } },
			settings: slot,
		} as HostPayload);

		expect(element.querySelector('.cms-video-figure video')?.getAttribute('src')).toBe(
			'/media/clip.mp4',
		);
		expect(slot.querySelector('textarea[id$="-caption"]')).not.toBeNull();
		expect(slot.querySelector('[id$="-alt"], [id$="-title"]')).toBeNull();
	});
});

describe('gallery block', () => {
	const assets = {
		a: { filename: 'a.jpg', url: '/media/a.jpg', kind: 'image' },
		b: { filename: 'b.jpg', url: '/media/b.jpg', kind: 'image' },
	};

	async function gallery(slot: HTMLElement, meta?: Meta) {
		document.body.append(slot);

		return media('cosray-image', {
			value: { zxx: [{ uid: 'a' }, { uid: 'b' }] },
			field: { name: 'images', presentation: 'block' },
			assets,
			settings: slot,
			...(meta ? { meta } : {}),
		} as HostPayload);
	}

	it('shows the tiles alone and keeps the drawer and the settings in the slot', async () => {
		const slot = document.createElement('div');
		const { element } = await gallery(slot, { ratio: { zxx: '4/3' } });
		const tiles = element.querySelector<HTMLElement>('.cms-gallery.is-block .tiles')!;

		expect(tiles.querySelectorAll('.tile')).toHaveLength(2);
		expect(tiles.style.getPropertyValue('--ratio')).toBe('4/3');
		expect(element.querySelector('.summary, .drawer, .cms-media-meta')).toBeNull();
		expect(slot.querySelector<HTMLSelectElement>('select')!.value).toBe('4/3');
		expect(slot.querySelectorAll('.strip .thumb')).toHaveLength(2);
		expect(slot.querySelector('.thumb.is-current')?.getAttribute('title')).toBe('a.jpg');
		expect(slot.querySelector('.cms-media-meta input[id$="-alt"]')).not.toBeNull();
	});

	it('follows ratio and crop live and reports them as the field meta', async () => {
		const slot = document.createElement('div');
		const { element, details } = await gallery(slot);
		const tiles = element.querySelector<HTMLElement>('.cms-gallery .tiles')!;
		const select = slot.querySelector<HTMLSelectElement>('select')!;
		const crop = slot.querySelector<HTMLInputElement>('input[type="checkbox"]')!;
		const value = { zxx: [{ uid: 'a' }, { uid: 'b' }] };

		select.value = '16/9';
		select.dispatchEvent(new Event('change', { bubbles: true }));
		await tick();
		expect(tiles.style.getPropertyValue('--ratio')).toBe('16/9');
		expect(tiles.classList.contains('has-ratio')).toBe(true);
		expect(details).toHaveBeenLastCalledWith({ value, meta: { ratio: { zxx: '16/9' } } });

		crop.click();
		await tick();
		expect(tiles.classList.contains('is-cropped')).toBe(true);
		expect(details).toHaveBeenLastCalledWith({
			value,
			meta: { ratio: { zxx: '16/9' }, crop: { zxx: true } },
		});

		select.value = 'auto';
		select.dispatchEvent(new Event('change', { bubbles: true }));
		await tick();
		expect(tiles.style.getPropertyValue('--ratio')).toBe('');
		expect(tiles.classList.contains('has-ratio')).toBe(false);
		expect(details).toHaveBeenLastCalledWith({ value, meta: { crop: { zxx: true } } });
	});

	it('steps through the images in the slot and edits the current one', async () => {
		const slot = document.createElement('div');
		const { changes } = await gallery(slot);

		slot.querySelector<HTMLButtonElement>('.step.next')!.click();
		await tick();
		expect(slot.querySelector('.thumb.is-current')?.getAttribute('title')).toBe('b.jpg');
		expect(slot.querySelector('.position')?.textContent).toBe('2 / 2');

		await enter(slot.querySelector<HTMLInputElement>('input[id$="-alt"]')!, 'Second');
		expect(changes).toHaveBeenLastCalledWith({
			zxx: [{ uid: 'a' }, { uid: 'b', meta: { alt: { zxx: 'Second' } } }],
		});
	});
});
