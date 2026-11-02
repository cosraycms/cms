import Sortable, { type SortableEvent } from 'sortablejs';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { BridgeSystem, UploadResult } from '../../src/lib/bridge';
import type { HostPayload } from '../../src/lib/host';
import type { FileItem, LocaleMap, Meta, RichtextDoc } from '../../src/types/data';
import { installBridge } from '../../src/lib/bridge-standalone';
import '../../src/elements/media.js';

vi.mock('../../src/lib/locale.js', () => ({
	__: (id: string) => id,
	__n: (one: string, many: string, n: number) => (n === 1 ? one : many),
}));

// The element updates synchronously; a microtask lets pending promises land.
const tick = () => Promise.resolve();

const system: BridgeSystem = {
	locale: 'en',
	defaultLocale: 'en',
	locales: [
		{ id: 'en', title: 'English' },
		{ id: 'de', title: 'Deutsch', fallback: 'en' },
	],
	customLocales: [],
	prefix: '/panel',
	assets: '/assets',
	debug: false,
	allowedFiles: { file: ['pdf'], image: ['png'], video: ['mp4'] },
};

type MediaElement = HTMLElement & HostPayload & { locale: string };

// The library dialog loads the server-rendered picker through htmx.
const ajax = vi.fn().mockResolvedValue(undefined);

beforeEach(() => {
	installBridge(system);
	vi.stubGlobal('htmx', { ajax });
	vi.stubGlobal(
		'ResizeObserver',
		class {
			observe() {}
			disconnect() {}
		},
	);
});

afterEach(async () => {
	document.querySelector<HTMLButtonElement>('.cms-modal [data-dialog-close]')?.click();
	document.body.replaceChildren();
	await tick();
	delete window.Cosray;
	vi.unstubAllGlobals();
	ajax.mockClear();
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
	element.querySelector<HTMLButtonElement>('.cms-file-row .edit')!.click();
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

function drag(target: Element, name: string, types: string[], files: File[] = []): void {
	const event = new Event(name, { bubbles: true, cancelable: true });
	Object.defineProperty(event, 'dataTransfer', { value: { types, files, items: [] } });
	target.dispatchEvent(event);
}

const captionDoc = (text: string): RichtextDoc => ({
	type: 'doc',
	content: [{ type: 'paragraph', content: [{ type: 'text', text }] }],
});

/** Pastes over a caption: the editor's own way in, as jsdom has no typing. */
function writeCaption(root: ParentNode, text: string, html = ''): void {
	const field = root.querySelector<HTMLElement>('.cms-inline-editor .ProseMirror')!;
	field.dispatchEvent(new KeyboardEvent('keydown', { key: 'a', ctrlKey: true, bubbles: true }));
	const paste = new Event('paste', { bubbles: true, cancelable: true });
	Object.assign(paste, {
		clipboardData: {
			types: [],
			getData: (type: string) => (type === 'text/plain' ? text : type === 'text/html' ? html : ''),
		},
	});
	field.dispatchEvent(paste);
}

const captionHint = (root: ParentNode) =>
	root.querySelector('.cms-inline-editor .placeholder')?.textContent;

const captionShown = (root: ParentNode) =>
	root.querySelector('.cms-inline-editor .ProseMirror')?.textContent;

async function action(label: string): Promise<void> {
	const button = Array.from(document.querySelectorAll<HTMLButtonElement>('.cms-modal button')).find(
		(button) => button.textContent?.trim() === label,
	);
	expect(button).toBeDefined();
	button!.click();
	await tick();
}

describe('the frame', () => {
	const catalog = {
		a: { filename: 'a.pdf', url: '/media/a.pdf', kind: 'file' },
		b: { filename: 'b.pdf', url: '/media/b.pdf', kind: 'file' },
	};

	async function files(max: number, count = 2, extra: Record<string, unknown> = {}) {
		return media('cosray-file', {
			value: { zxx: [{ uid: 'a' }, { uid: 'b' }].slice(0, count) },
			field: { name: 'downloads', limit: { min: 0, max }, ...extra },
			assets: catalog,
		});
	}

	it('opens the picker and the library from the bar and counts against the limit', async () => {
		const { element } = await files(5);
		const bar = element.querySelector<HTMLElement>('.cms-media-field > .bar')!;
		const picker = element.querySelector<HTMLInputElement>('input[type="file"]')!;
		const click = vi.spyOn(picker, 'click').mockImplementation(() => {});

		expect(bar.querySelector('.tally')?.textContent).toBe('2 / 5');
		expect(bar.querySelector('.choose')?.textContent).toBe('upload:choose-files');

		bar.querySelector<HTMLButtonElement>('.choose')!.click();
		expect(click).toHaveBeenCalledOnce();

		bar.querySelector<HTMLButtonElement>('.browse')!.click();
		await tick();
		expect(document.querySelector('.cms-modal')).not.toBeNull();
		expect(ajax).toHaveBeenCalledWith('GET', '/panel/media/picker', expect.anything());
	});

	it('is the bar alone while empty', async () => {
		const { element } = await files(1, 0);

		expect(element.querySelector('.cms-media-field.is-empty > .bar')).not.toBeNull();
		expect(element.querySelector('.cms-media-field > .body')).toBeNull();
		expect(element.querySelector('.choose')?.textContent).toBe('upload:choose-file');
		expect(element.querySelector('.tally')).toBeNull();
	});

	it('closes the bar actions once the field is full', async () => {
		const { element } = await files(2);

		expect(element.querySelector<HTMLButtonElement>('.choose')!.disabled).toBe(true);
		expect(element.querySelector<HTMLButtonElement>('.browse')!.disabled).toBe(true);
	});

	it('becomes the drop zone while files hover and uploads what lands', async () => {
		const upload = vi
			.spyOn(window.Cosray!, 'upload')
			.mockResolvedValue({ ok: true, uid: 'dropped', filename: 'dropped.pdf' });
		const { element, changes } = await files(5);
		const frame = element.querySelector<HTMLElement>('.cms-media-field')!;
		const bar = frame.querySelector<HTMLElement>('.bar')!;
		const dropped = new File(['x'], 'dropped.pdf', { type: 'application/pdf' });

		drag(frame, 'dragenter', ['text/plain']);
		await tick();
		expect(frame.classList.contains('is-dragging')).toBe(false);

		drag(frame, 'dragenter', ['Files']);
		drag(bar, 'dragenter', ['Files']);
		await tick();
		expect(frame.classList.contains('is-dragging')).toBe(true);
		expect(frame.querySelector('.drop')?.textContent?.trim()).toBe('media:drop-to-upload');

		drag(bar, 'dragleave', ['Files']);
		await tick();
		expect(frame.classList.contains('is-dragging')).toBe(true);

		drag(frame, 'dragleave', ['Files']);
		await tick();
		expect(frame.classList.contains('is-dragging')).toBe(false);

		drag(frame, 'dragenter', ['Files']);
		drag(frame, 'drop', ['Files'], [dropped]);
		await vi.waitFor(() => {
			expect(changes).toHaveBeenLastCalledWith({
				zxx: [{ uid: 'a' }, { uid: 'b' }, { uid: 'dropped' }],
			});
		});
		expect(upload).toHaveBeenCalledExactlyOnceWith('file', dropped);
		expect(frame.classList.contains('is-dragging')).toBe(false);
	});

	it('says that a drop replaces the file of a filled single field', async () => {
		const { element } = await files(1, 1);
		const frame = element.querySelector<HTMLElement>('.cms-media-field')!;

		drag(frame, 'dragenter', ['Files']);
		await tick();

		expect(frame.querySelector('.drop')?.textContent?.trim()).toBe('upload:drop-to-replace');
	});

	it('renders a read-only field without the bar and names an empty one', async () => {
		const { element } = await files(5, 2, { immutable: true });
		const frame = element.querySelector<HTMLElement>('.cms-media-field.is-readonly')!;

		expect(frame.querySelector('.bar')).toBeNull();
		expect(frame.querySelector('input[type="file"]')).toBeNull();
		expect(frame.querySelector('.body')).not.toBeNull();

		const { element: blank } = await files(5, 0, { immutable: true });

		expect(blank.querySelector('.cms-media-field > .none')?.textContent).toBe('media:empty-many');
	});
});

describe('filled single field', () => {
	const assets = {
		cover: { filename: 'cover.jpg', url: '/media/cover.jpg', kind: 'image' },
		guide: { filename: 'guide.pdf', url: '/media/guide.pdf', kind: 'file' },
		clip: { filename: 'clip.mp4', url: '/media/clip.mp4', kind: 'video' },
	};

	it.each([
		{ label: 'image card', tag: 'cosray-image', uid: 'cover' },
		{ label: 'image figure', tag: 'cosray-image', uid: 'cover', presentation: 'block' },
		{ label: 'file row', tag: 'cosray-file', uid: 'guide' },
		{ label: 'video row', tag: 'cosray-video', uid: 'clip' },
		{ label: 'video figure', tag: 'cosray-video', uid: 'clip', presentation: 'block' },
	])('replaces its file from the menu of the $label', async ({ tag, uid, presentation }) => {
		const { element } = await media(tag, {
			value: { zxx: [{ uid }] },
			field: { name: 'single', limit: { min: 0, max: 1 }, presentation },
			assets,
		} as HostPayload);
		const picker = element.querySelector<HTMLInputElement>('input[type="file"]')!;
		const click = vi.spyOn(picker, 'click').mockImplementation(() => {});
		const [library, device] = element.querySelectorAll<HTMLButtonElement>(
			'.cms-replace-menu button',
		);

		expect(element.querySelector('.cms-media-field > .bar')).toBeNull();

		device.click();
		expect(click).toHaveBeenCalledOnce();

		library.click();
		await tick();
		expect(document.querySelector('.cms-modal')).not.toBeNull();
	});
});

describe('single image', () => {
	const cover = { filename: 'cover.jpg', url: '/media/cover.jpg', kind: 'image' };

	it('removes the image through the card and falls back to the bar', async () => {
		const { element, changes } = await media('cosray-image', {
			value: { zxx: [{ uid: 'cover' }] },
			field: { name: 'cover', limit: { min: 0, max: 1 } },
			assets: { cover },
		});
		const card = element.querySelector<HTMLElement>('.cms-image-card')!;

		card.querySelector<HTMLButtonElement>('.discard')!.click();
		await tick();

		expect(changes).toHaveBeenCalledExactlyOnceWith({ zxx: [] });
		expect(element.querySelector('.cms-image-card')).toBeNull();
		expect(element.querySelector('.cms-media-field.is-empty > .bar')).not.toBeNull();
	});

	it('keeps the picture on a read-only card and shows its texts read-only', async () => {
		const { element } = await media('cosray-image', {
			value: { zxx: [{ uid: 'cover', meta: { alt: { zxx: 'A kettle' } } }] },
			field: { name: 'cover', limit: { min: 0, max: 1 }, immutable: true },
			assets: { cover },
		});
		const card = element.querySelector<HTMLElement>('.cms-image-card')!;

		expect(card.querySelector('img')?.getAttribute('src')).toBe('/media/cover.jpg');
		expect(card.querySelector('.discard, .replace, .edit')).toBeNull();

		card.querySelector<HTMLButtonElement>('.controls button')!.click();
		await tick();
		const alt = document.querySelector<HTMLTextAreaElement>('.cms-modal textarea[id$="-alt"]')!;

		expect(alt.value).toBe('A kettle');
		expect(alt.readOnly).toBe(true);
		expect(document.querySelector('.cms-modal .replace')).toBeNull();
		expect(document.activeElement?.textContent).toBe('common:close');
	});
});

describe('image details', () => {
	const assets = {
		cover: {
			filename: 'cover.jpg',
			url: '/media/cover.jpg',
			previewUrl: '/media/cover-preview.jpg',
			kind: 'image',
			bytes: 862208,
			width: 2400,
			height: 1600,
		},
		fresh: { filename: 'fresh.png', url: '/media/fresh.png', kind: 'image' },
	};

	async function details(item: FileItem = { uid: 'cover' }, extra: Record<string, unknown> = {}) {
		const opened = await media('cosray-image', {
			value: { zxx: [item] },
			field: { name: 'cover', limit: { min: 0, max: 1 }, caption: true, ...extra },
			assets,
		});
		opened.element.querySelector<HTMLButtonElement>('.cms-image-card .edit')!.click();
		await tick();

		return opened;
	}

	function field(name: string): HTMLTextAreaElement {
		return document.querySelector<HTMLTextAreaElement>(`.cms-modal textarea[id$="-${name}"]`)!;
	}

	it('opens from the picture too and starts on the alt text', async () => {
		const { element } = await media('cosray-image', {
			value: { zxx: [{ uid: 'cover' }] },
			field: { name: 'cover', limit: { min: 0, max: 1 } },
			assets,
		});

		element.querySelector<HTMLElement>('.cms-image-card .thumb')!.click();
		await tick();

		expect(document.activeElement).toBe(field('alt'));
	});

	it('shows the image with its facts next to the texts', async () => {
		await details();
		const modal = document.querySelector<HTMLElement>('.cms-modal')!;
		const facts = Array.from(modal.querySelectorAll('.facts > div')).map((row) => [
			row.querySelector('dt')?.textContent,
			row.querySelector('dd')?.textContent,
		]);

		expect(modal.querySelector('.modal-header .filename')?.textContent).toBe('cover.jpg');
		expect(modal.querySelector('.preview img')?.getAttribute('src')).toBe(
			'/media/cover-preview.jpg',
		);
		expect(facts.slice(0, 3)).toEqual([
			['media:size', '842.0 KB'],
			['media:dimensions', '2400 × 1600 px'],
			['media:format', 'JPG'],
		]);
		expect(modal.querySelector<HTMLInputElement>('.address input')?.value).toBe(
			new URL('/media/cover.jpg', location.href).href,
		);
		expect(modal.querySelector('[id$="-title"]')).toBeNull();
	});

	describe('link', () => {
		const row = () => document.querySelector<HTMLElement>('.cms-modal .cms-image-link');
		const rowButton = (label: string) =>
			[...row()!.querySelectorAll('button')].find(
				(entry) => entry.textContent === label || entry.title === label,
			)!;

		it('offers no link unless the field asks for it', async () => {
			await details();

			expect(row()).toBeNull();
		});

		it('stores an address with its target for this use', async () => {
			const { changes } = await details(
				{ uid: 'cover', meta: { alt: { zxx: 'A kettle' } } },
				{ link: true },
			);

			rowButton('link:add').click();
			await tick();
			const input = document.querySelector<HTMLInputElement>(
				'.cms-modal-link-manual-input-wrap input',
			)!;
			input.value = 'https://example.com';
			input.dispatchEvent(new Event('input', { bubbles: true }));
			document.querySelector<HTMLInputElement>('.cms-modal input[type="checkbox"]')!.click();
			[...document.querySelectorAll<HTMLButtonElement>('.cms-modal button')]
				.find((entry) => entry.textContent === 'link:add' && !entry.closest('.cms-image-link'))!
				.click();
			await tick();

			expect(row()?.querySelector('.cms-image-link-text')?.textContent).toBe('https://example.com');

			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0].meta).toEqual({
				alt: { zxx: 'A kettle' },
				link: { href: 'https://example.com', target: '_blank' },
			});
		});

		it('keeps the rel values an editor adds to an address', async () => {
			const { changes } = await details(
				{
					uid: 'cover',
					meta: { alt: { zxx: 'A kettle' }, link: { href: '/a', rel: 'sponsored' } },
				},
				{ link: true },
			);

			rowButton('common:edit').click();
			await tick();
			const advanced = document.querySelector<HTMLDetailsElement>('.cms-modal-link-advanced')!;

			expect(advanced.open).toBe(true);
			expect(advanced.hidden).toBe(false);

			advanced.querySelector<HTMLInputElement>('input[value="nofollow"]')!.click();
			[...document.querySelectorAll<HTMLButtonElement>('.cms-modal button')]
				.find((entry) => entry.textContent === 'link:add' && !entry.closest('.cms-image-link'))!
				.click();
			await tick();
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0].meta?.link).toEqual({
				href: '/a',
				rel: 'nofollow sponsored',
			});
		});

		it('collapses the advanced options and offers them for addresses only', async () => {
			await details({ uid: 'cover' }, { link: true });

			rowButton('link:add').click();
			await tick();
			const advanced = document.querySelector<HTMLDetailsElement>('.cms-modal-link-advanced')!;

			expect(advanced.open).toBe(false);
			expect(advanced.querySelectorAll('input:checked')).toHaveLength(0);

			[...document.querySelectorAll<HTMLButtonElement>('.cms-modal .tab')][1]!.click();

			expect(advanced.hidden).toBe(true);
		});

		it('refuses an address that can run code', async () => {
			await details({ uid: 'cover' }, { link: true });

			rowButton('link:add').click();
			await tick();
			const input = document.querySelector<HTMLInputElement>(
				'.cms-modal-link-manual-input-wrap input',
			)!;
			input.value = 'javascript:alert(1)';
			input.dispatchEvent(new Event('input', { bubbles: true }));

			const confirm = [...document.querySelectorAll<HTMLButtonElement>('.cms-modal button')].find(
				(entry) => entry.textContent === 'link:add' && !entry.closest('.cms-image-link'),
			);

			expect(confirm?.disabled).toBe(true);
		});

		it('removes a link', async () => {
			const { changes } = await details(
				{ uid: 'cover', meta: { alt: { zxx: 'A kettle' }, link: { href: '/about' } } },
				{ link: true },
			);

			rowButton('common:remove').click();
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0].meta).toEqual({ alt: { zxx: 'A kettle' } });
		});

		it('warns while a linked image has no alt text', async () => {
			await details({ uid: 'cover', meta: { link: { href: '/about' } } }, { link: true });

			expect(row()?.querySelector('.warning')?.textContent).toBe('image:link-no-alt');

			field('alt').value = 'A kettle';
			field('alt').dispatchEvent(new Event('input', { bubbles: true }));

			expect(row()?.querySelector('.warning')).toBeNull();
		});

		it('shows where the image links to on its card', async () => {
			const opened = await media('cosray-image', {
				value: { zxx: [{ uid: 'cover', meta: { link: { href: '/about' } } }] },
				field: { name: 'cover', limit: { min: 0, max: 1 }, link: true },
				assets,
			});

			const texts = [...opened.element.querySelectorAll('.cms-image-card .texts > div')];

			expect(texts.at(-1)?.textContent).toBe('image:link:/about');
		});
	});

	describe('required parts', () => {
		const requirement = (name: string) =>
			document.querySelector(`.cms-modal label[for$="-${name}"] .requirement`)?.textContent;

		it('marks them in the dialog instead of calling them optional', async () => {
			await details({ uid: 'cover' }, { requiredItems: ['alt', 'caption'], attribution: true });

			expect(requirement('alt')).toBe('(field:required)');
			expect(requirement('caption')).toBe('(field:required)');
			expect(requirement('attribution')).toBeUndefined();
			expect(document.querySelector('.cms-modal label[for$="-caption"] .remark')).toBeNull();
		});

		it('marks a required link', async () => {
			await details({ uid: 'cover' }, { link: true, requiredItems: ['link'] });

			expect(document.querySelector('.cms-image-link .requirement')?.textContent).toBe(
				'(field:required)',
			);
		});

		it('offers to add a required text on the card', async () => {
			const { element } = await media('cosray-image', {
				value: { zxx: [{ uid: 'cover', meta: { alt: { zxx: 'A kettle' } } }] },
				field: {
					name: 'cover',
					limit: { min: 0, max: 1 },
					caption: true,
					link: true,
					requiredItems: ['caption', 'link'],
				},
				assets: { cover: { ...assets.cover, meta: {} } },
			});
			const rows = [...element.querySelectorAll('.cms-image-card .texts > div')].map(
				(row) => row.textContent,
			);

			expect(rows).toEqual([
				'image:alt-text:A kettle',
				'image:caption:image:alt-text-add',
				'image:link:image:alt-text-add',
			]);
		});

		it('marks a gallery tile that lacks one', async () => {
			const { element } = await media('cosray-image', {
				value: {
					zxx: [
						{ uid: 'cover', meta: { alt: { zxx: 'A kettle' } } },
						{ uid: 'plain' },
						{ uid: 'shared' },
					],
				},
				field: { name: 'gallery', requiredItems: ['alt'] },
				assets: {
					cover: { ...assets.cover, meta: {} },
					plain: { ...assets.cover, meta: {} },
					shared: { ...assets.cover, meta: { alt: { en: 'From the catalog' } } },
				},
				locales: { all: [{ id: 'en', title: 'English' }], default: 'en' },
			} as HostPayload);
			const tiles = [...element.querySelectorAll('.tile')];

			expect(tiles.map((tile) => tile.querySelector('.tile-missing') !== null)).toEqual([
				false,
				true,
				false,
			]);
			expect(tiles[1]?.querySelector('.tile-missing')?.getAttribute('aria-label')).toBe(
				'image:missing image:alt-text',
			);
		});

		it('names what a block figure lacks', async () => {
			const { element } = await media('cosray-image', {
				value: { zxx: [{ uid: 'cover' }] },
				field: {
					name: 'image',
					limit: { min: 0, max: 1 },
					presentation: 'block',
					requiredItems: ['alt'],
				},
				assets: { cover: { ...assets.cover, meta: {} } },
			});

			expect(element.querySelector('.cms-media-missing')?.textContent).toBe(
				'image:missing image:alt-text',
			);
		});

		it('asks nothing of the other lists of an asymmetric field', async () => {
			const { element } = await media(
				'cosray-image',
				{
					value: { en: [{ uid: 'cover' }], de: [{ uid: 'cover' }] },
					field: {
						name: 'gallery',
						translate: true,
						translateMode: 'asymmetric',
						requiredItems: ['alt'],
					},
					assets: { cover: { ...assets.cover, meta: {} } },
					locales: {
						all: [
							{ id: 'en', title: 'English' },
							{ id: 'de', title: 'Deutsch' },
						],
						default: 'en',
					},
				} as HostPayload,
				'de',
			);

			expect(element.querySelector('.tile-missing')).toBeNull();
		});
	});

	it('keeps stored texts the field does not offer', async () => {
		const { changes } = await details(
			{ uid: 'cover', meta: { caption: { zxx: 'Kept' }, title: { zxx: 'Old' } } },
			{ caption: false },
		);

		expect(document.querySelector('.cms-modal [id$="-caption"]')).toBeNull();

		field('alt').value = 'A kettle';
		field('alt').dispatchEvent(new Event('input', { bubbles: true }));
		await action('common:apply');

		expect(changes.mock.lastCall?.[0].zxx[0].meta).toEqual({
			alt: { zxx: 'A kettle' },
			caption: { zxx: 'Kept' },
			title: { zxx: 'Old' },
		});
	});

	it('edits the attribution as one neutral text over the catalog default', async () => {
		const { changes } = await details({ uid: 'cover' }, { attribution: true });
		const input = () =>
			document.querySelector<HTMLInputElement>('.cms-modal input[id$="-attribution"]')!;

		input().value = '  ';
		input().dispatchEvent(new Event('input', { bubbles: true }));
		await action('common:apply');
		expect(changes.mock.lastCall?.[0].zxx[0]).toEqual({ uid: 'cover' });

		document.querySelector<HTMLButtonElement>('.cms-image-card .edit')!.click();
		await tick();
		input().value = '© Jane Doe';
		input().dispatchEvent(new Event('input', { bubbles: true }));
		await action('common:apply');

		expect(changes.mock.lastCall?.[0].zxx[0].meta).toEqual({ attribution: '© Jane Doe' });
	});

	it('shows the catalog attribution as the default', async () => {
		await media('cosray-image', {
			value: { zxx: [{ uid: 'cover' }] },
			field: { name: 'cover', limit: { min: 0, max: 1 }, attribution: true },
			assets: { cover: { ...assets.cover, meta: { attribution: '© Studio' } } },
		});
		document.querySelector<HTMLButtonElement>('.cms-image-card .edit')!.click();
		await tick();
		const input = document.querySelector<HTMLInputElement>('.cms-modal input[id$="-attribution"]')!;

		expect(input.placeholder).toBe('© Studio');
		expect(input.parentElement?.querySelector('.fallback')?.textContent).toBe('image:from-library');
	});

	it('applies the texts, keeps other meta and leaves the catalog alone', async () => {
		const { element, changes } = await details({
			uid: 'cover',
			meta: { title: { zxx: 'Kept title' } },
		});
		const catalog = structuredClone(element.assets);

		await enter(field('alt'), 'A kettle');
		writeCaption(document, 'Under the image');
		expect(changes).not.toHaveBeenCalled();
		await action('common:apply');

		expect(changes).toHaveBeenCalledExactlyOnceWith({
			zxx: [
				{
					uid: 'cover',
					meta: {
						title: { zxx: 'Kept title' },
						alt: { zxx: 'A kettle' },
						caption: { zxx: captionDoc('Under the image') },
					},
				},
			],
		});
		expect(element.assets).toEqual(catalog);
	});

	it('drops an emptied text so the catalog applies again', async () => {
		const { changes } = await details({ uid: 'cover', meta: { alt: { zxx: 'Old' } } });

		await enter(field('alt'), '');
		await action('common:apply');

		expect(changes).toHaveBeenCalledExactlyOnceWith({ zxx: [{ uid: 'cover' }] });
	});

	it('discards the draft on cancel and reports nothing when applied unchanged', async () => {
		const { element, changes } = await details();

		await enter(field('alt'), 'Discarded');
		await action('common:cancel');

		element.querySelector<HTMLButtonElement>('.cms-image-card .edit')!.click();
		await tick();
		expect(field('alt').value).toBe('');
		await action('common:apply');

		expect(changes).not.toHaveBeenCalled();
	});

	describe('caption', () => {
		const marked = (text: string, type: string): RichtextDoc => ({
			type: 'doc',
			content: [{ type: 'paragraph', content: [{ type: 'text', text, marks: [{ type }] }] }],
		});

		it('keeps the three marks of a paste and nothing else', async () => {
			const { changes } = await details();

			writeCaption(
				document,
				'plain',
				'<h2>Head</h2><p>one <em>two</em></p><ul><li>three</li></ul><p><a href="/x">link</a> <img src="/i.png"></p>',
			);
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0].meta?.caption).toEqual({
				zxx: {
					type: 'doc',
					content: [
						{
							type: 'paragraph',
							content: [
								{ type: 'text', text: 'Head one ' },
								{ type: 'text', text: 'two', marks: [{ type: 'italic' }] },
								{ type: 'text', text: ' three link' },
							],
						},
					],
				},
			});
		});

		it('stays on one line', async () => {
			await details();
			const field = document.querySelector<HTMLElement>('.cms-modal .ProseMirror')!;

			writeCaption(document, 'One');
			field.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

			expect(field.querySelectorAll('p').length).toBe(1);
		});

		it('marks text from its tools', async () => {
			const { changes } = await details();

			writeCaption(document, 'Sudhaus');
			document
				.querySelector('.cms-modal .ProseMirror')!
				.dispatchEvent(new KeyboardEvent('keydown', { key: 'a', ctrlKey: true, bubbles: true }));
			document
				.querySelector<HTMLButtonElement>('.cms-inline-editor .tools button:nth-child(2)')!
				.click();
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0].meta?.caption).toEqual({
				zxx: marked('Sudhaus', 'italic'),
			});
		});

		it('drops a caption that was emptied', async () => {
			const { changes } = await details({
				uid: 'cover',
				meta: { caption: { zxx: captionDoc('Old') } },
			});

			const field = document.querySelector<HTMLElement>('.cms-modal .ProseMirror')!;
			field.dispatchEvent(new KeyboardEvent('keydown', { key: 'a', ctrlKey: true, bubbles: true }));
			field.dispatchEvent(new KeyboardEvent('keydown', { key: 'Backspace', bubbles: true }));
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0]).toEqual({ uid: 'cover' });
		});

		it('opens a plain caption from before marks as text', async () => {
			await details({ uid: 'cover', meta: { caption: { zxx: 'Plain & old' } } });

			expect(captionShown(document)).toBe('Plain & old');
		});

		it('shows a fallback document as plain placeholder text', async () => {
			await media('cosray-image', {
				value: { zxx: [{ uid: 'cover' }] },
				field: { name: 'cover', limit: { min: 0, max: 1 }, caption: true },
				assets: {
					cover: { ...assets.cover, meta: { caption: { en: marked('From the media', 'bold') } } },
				},
			});
			document.querySelector<HTMLButtonElement>('.cms-image-card .edit')!.click();
			await tick();

			expect(captionHint(document)).toBe('From the media');
		});

		it('shows its marks on the card', async () => {
			const { element } = await media('cosray-image', {
				value: { zxx: [{ uid: 'cover', meta: { caption: { zxx: marked('Sudhaus', 'italic') } } }] },
				field: { name: 'cover', limit: { min: 0, max: 1 }, caption: true },
				assets,
			});

			expect(element.querySelector('.cms-image-card .texts em')?.textContent).toBe('Sudhaus');
		});

		it('is read-only in a read-only field', async () => {
			const { element } = await media('cosray-image', {
				value: { zxx: [{ uid: 'cover', meta: { caption: { zxx: 'Kept' } } }] },
				field: { name: 'cover', limit: { min: 0, max: 1 }, caption: true, immutable: true },
				assets,
			});
			element.querySelector<HTMLElement>('.cms-image-card .thumb')!.click();
			await tick();

			expect(
				document.querySelector('.cms-modal .ProseMirror')?.getAttribute('contenteditable'),
			).toBe('false');
			expect(document.querySelector('.cms-inline-editor .tools')).toBeNull();
		});
	});

	describe('focal point', () => {
		const status = () => document.querySelector('.cms-modal .focus-status .text')?.textContent;
		const marker = () => document.querySelector<HTMLElement>('.cms-modal .marker')!;
		const reset = () => document.querySelector<HTMLButtonElement>('.cms-modal .reset')!;
		const key = (name: string, shiftKey = false) =>
			marker().dispatchEvent(new KeyboardEvent('keydown', { key: name, shiftKey, bubbles: true }));
		const catalog = { ...assets, cover: { ...assets.cover, meta: { focal: { x: 0.3, y: 0.4 } } } };

		async function withCatalog(item: FileItem = { uid: 'cover' }) {
			const opened = await media('cosray-image', {
				value: { zxx: [item] },
				field: { name: 'cover', limit: { min: 0, max: 1 }, caption: true },
				assets: catalog,
			});
			opened.element.querySelector<HTMLButtonElement>('.cms-image-card .edit')!.click();
			await tick();

			return opened;
		}

		it('asks for a point while there is none', async () => {
			await details();

			expect(status()).toBe('image:focus-set');
			expect(marker().classList.contains('is-unset')).toBe(true);
			expect(reset().hidden).toBe(true);
		});

		it('shows the catalog point as inherited', async () => {
			await withCatalog();

			expect(status()).toBe('image:focus: 30 % / 40 % · image:from-library');
			expect(marker().classList.contains('is-inherited')).toBe(true);
			expect(marker().style.left).toBe('30%');
			expect(reset().hidden).toBe(true);
		});

		it('places the point where the pointer lands', async () => {
			const { changes } = await details();
			const frame = document.querySelector<HTMLElement>('.cms-modal .frame')!;
			frame.getBoundingClientRect = () =>
				({ left: 100, top: 50, width: 200, height: 100 }) as DOMRect;

			frame.dispatchEvent(
				new MouseEvent('pointerdown', { clientX: 150, clientY: 125, button: 0, bubbles: true }),
			);
			expect(status()).toBe('image:focus: 25 % / 75 %');

			frame.dispatchEvent(new MouseEvent('pointermove', { clientX: 200, clientY: 100 }));
			frame.dispatchEvent(new MouseEvent('pointerup', {}));
			frame.dispatchEvent(new MouseEvent('pointermove', { clientX: 300, clientY: 150 }));
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0].meta).toEqual({ focal: { x: 0.5, y: 0.5 } });
		});

		it('moves by keys from the inherited point, and goes back to it', async () => {
			const { changes } = await withCatalog();

			key('ArrowRight');
			key('ArrowDown', true);
			expect(status()).toBe('image:focus: 31 % / 50 %');
			expect(marker().getAttribute('aria-valuetext')).toBe('31 % / 50 %');
			expect(marker().classList.contains('is-inherited')).toBe(false);
			expect(reset().hidden).toBe(false);

			reset().click();
			expect(status()).toBe('image:focus: 30 % / 40 % · image:from-library');
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0]).toEqual({ uid: 'cover' });
		});

		it('keeps the point at the edge of the image', async () => {
			await details({ uid: 'cover', meta: { focal: { x: 0.95, y: 0.5 } } });

			key('ArrowRight', true);

			expect(status()).toBe('image:focus: 100 % / 50 %');
		});

		it('merges the point with the texts, whichever comes last', async () => {
			const { changes } = await details();

			field('alt').value = 'First';
			field('alt').dispatchEvent(new Event('input', { bubbles: true }));
			key('ArrowRight');
			writeCaption(document, 'Last');
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0].meta).toEqual({
				alt: { zxx: 'First' },
				caption: { zxx: captionDoc('Last') },
				focal: { x: 0.51, y: 0.5 },
			});
		});

		it('only shows the point of a read-only image', async () => {
			const { element } = await media('cosray-image', {
				value: { zxx: [{ uid: 'cover', meta: { focal: { x: 0.3, y: 0.4 } } }] },
				field: { name: 'cover', limit: { min: 0, max: 1 }, immutable: true },
				assets,
			});
			element.querySelector<HTMLElement>('.cms-image-card .thumb')!.click();
			await tick();

			expect(marker().hasAttribute('role')).toBe(false);
			expect(document.querySelector('.cms-modal .reset')).toBeNull();
			expect(status()).toBe('image:focus: 30 % / 40 %');
		});

		it('shows the same cut in the previews as the site', async () => {
			await details({ uid: 'cover', meta: { focal: { x: 1, y: 0 } } });
			const square = document.querySelectorAll<HTMLElement>('.cms-modal .shot .box')[1];
			const image = square.querySelector('img')!;

			// The 2400 by 1600 image at its right edge: a third of its width lies to the left.
			expect(image.style.width).toBe('150%');
			expect(Number.parseFloat(image.style.left)).toBeCloseTo(-50);
		});
	});

	describe('crop', () => {
		const box = () => document.querySelector<HTMLElement>('.cms-modal .crop-box')!;
		const frame = () => document.querySelector<HTMLElement>('.cms-modal .frame')!;
		const status = () => document.querySelector('.cms-modal .focus-status .text')?.textContent;
		const removal = () => document.querySelector<HTMLButtonElement>('.cms-modal .reset')!;
		const jpeg = { ...assets, cover: { ...assets.cover, mime: 'image/jpeg' } };

		async function cropping(item: FileItem = { uid: 'cover' }, extra: object = {}) {
			const opened = await media('cosray-image', {
				value: { zxx: [item] },
				field: { name: 'cover', limit: { min: 0, max: 1 }, cropping: true, ...extra },
				assets: jpeg,
			});
			opened.element.querySelector<HTMLButtonElement>('.cms-image-card .edit')?.click();
			await tick();
			document.querySelector<HTMLButtonElement>('.cms-modal [data-mode="crop"]')?.click();

			return opened;
		}

		function drag(target: Element, from: [number, number], to: [number, number]) {
			frame().getBoundingClientRect = () =>
				({ left: 0, top: 0, width: 100, height: 100 }) as DOMRect;
			target.dispatchEvent(
				new MouseEvent('pointerdown', {
					clientX: from[0],
					clientY: from[1],
					button: 0,
					bubbles: true,
				}),
			);
			frame().dispatchEvent(new MouseEvent('pointermove', { clientX: to[0], clientY: to[1] }));
			frame().dispatchEvent(new MouseEvent('pointerup', {}));
		}

		it('offers the tool only where the site can deliver it', async () => {
			await details();
			expect(document.querySelector('.cms-modal [data-mode="crop"]')).toBeNull();
			document.querySelector<HTMLButtonElement>('.cms-modal [data-dialog-close]')?.click();
			await tick();

			await media('cosray-image', {
				value: { zxx: [{ uid: 'gif' }] },
				field: { name: 'cover', limit: { min: 0, max: 1 }, cropping: true },
				assets: { gif: { ...assets.cover, mime: 'image/gif' } },
			});
			document.querySelector<HTMLButtonElement>('.cms-image-card .edit')!.click();
			await tick();
			expect(document.querySelector('.cms-modal [data-mode="crop"]')).toBeNull();
		});

		it('draws a crop on the image', async () => {
			const { changes } = await cropping();

			expect(status()).toBe('image:crop-hint');
			drag(frame(), [10, 20], [60, 70]);
			expect(status()).toBe('1200 × 800 px');
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0].meta).toEqual({
				crop: { x: 0.1, y: 0.2, w: 0.5, h: 0.5 },
			});
		});

		it('takes a click for a click', async () => {
			const { changes } = await cropping();

			drag(frame(), [10, 20], [10, 20]);
			await action('common:apply');

			expect(changes).not.toHaveBeenCalled();
		});

		it('moves and resizes a crop by pointer and keys', async () => {
			const { changes } = await cropping({
				uid: 'cover',
				meta: { crop: { x: 0.1, y: 0.1, w: 0.5, h: 0.5 } },
			});

			drag(box(), [30, 30], [40, 30]);
			drag(box().querySelector('.se')!, [70, 60], [80, 70]);
			box()
				.querySelector('.nw')!
				.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
			box().dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0].meta).toEqual({
				crop: { x: 0.21, y: 0.11, w: 0.59, h: 0.6 },
			});
		});

		it('fits the crop to a chosen shape and keeps to it', async () => {
			const { changes } = await cropping();
			const select = document.querySelector<HTMLSelectElement>('.cms-modal .ratio select')!;

			select.value = '1/1';
			select.dispatchEvent(new Event('change'));
			await action('common:apply');

			// Square on a 3:2 image: all of its height and two thirds of its width.
			expect(changes.mock.lastCall?.[0].zxx[0].meta).toEqual({
				crop: { x: 0.1667, y: 0, w: 0.6667, h: 1 },
			});
		});

		it('removes the crop', async () => {
			const { changes } = await cropping({
				uid: 'cover',
				meta: { crop: { x: 0, y: 0, w: 0.5, h: 0.5 } },
			});

			removal().click();
			await action('common:apply');

			expect(changes.mock.lastCall?.[0].zxx[0]).toEqual({ uid: 'cover' });
		});

		it('keeps its draft and tool while stepping through a gallery', async () => {
			const { element, changes } = await media('cosray-image', {
				value: { zxx: [{ uid: 'one' }, { uid: 'two' }] },
				field: { name: 'gallery', cropping: true },
				assets: { one: jpeg.cover, two: jpeg.cover },
			});
			element.querySelector<HTMLButtonElement>('.cms-gallery .edit')!.click();
			await tick();
			document.querySelector<HTMLButtonElement>('.cms-modal [data-mode="crop"]')!.click();
			drag(frame(), [0, 0], [50, 50]);
			document.querySelector<HTMLButtonElement>('.cms-modal .step.next')!.click();

			expect(status()).toBe('image:crop-hint');

			document.querySelector<HTMLButtonElement>('.cms-modal .step.prev')!.click();

			expect(status()).toBe('1200 × 800 px');

			await action('common:apply');
			expect(changes.mock.lastCall?.[0].zxx[0].meta?.crop).toEqual({ x: 0, y: 0, w: 0.5, h: 0.5 });
			expect(changes.mock.lastCall?.[0].zxx[1]).toEqual({ uid: 'two' });
		});

		it('shows the crop on the card', async () => {
			const { element } = await media('cosray-image', {
				value: { zxx: [{ uid: 'cover', meta: { crop: { x: 0.5, y: 0, w: 0.5, h: 1 } } }] },
				field: { name: 'cover', limit: { min: 0, max: 1 } },
				assets,
			});
			const thumb = element.querySelector<HTMLElement>('.cms-image-card .thumb')!;
			const image = thumb.querySelector<HTMLImageElement>('.cms-crop-window img')!;

			// Half of a 3:2 image is 3:4.
			expect(thumb.style.getPropertyValue('--thumb-ratio')).toBe('0.75');
			expect(image.style.width).toBe('200%');
			expect(image.style.left).toBe('-100%');
		});
	});

	describe('card texts', () => {
		const texts = (element: HTMLElement) =>
			[...element.querySelectorAll('.cms-image-card .texts div')].map(
				(row) => `${row.querySelector('dt')?.textContent} ${row.querySelector('dd')?.textContent}`,
			);
		const card = (item: FileItem, extra: Record<string, unknown> = {}) =>
			media('cosray-image', {
				value: { zxx: [item] },
				field: { name: 'cover', limit: { min: 0, max: 1 }, caption: true, ...extra },
				assets,
			});

		it('shows only the texts the field offers', async () => {
			const meta = {
				alt: { zxx: 'A kettle' },
				caption: { zxx: 'In the brewhouse' },
				attribution: '© Jane Doe',
			};
			const plain = await card({ uid: 'cover', meta }, { caption: false });
			const offered = await card({ uid: 'cover', meta }, { attribution: true });

			expect(texts(plain.element)).toEqual(['image:alt-text: A kettle']);
			expect(texts(offered.element)).toEqual([
				'image:alt-text: A kettle',
				'image:caption: In the brewhouse',
				'image:attribution: © Jane Doe',
			]);
		});

		it('shows the alt text and caption', async () => {
			const { element } = await card({
				uid: 'cover',
				meta: { alt: { zxx: 'A kettle' }, caption: { zxx: 'In the brewhouse' } },
			});

			expect(texts(element)).toEqual([
				'image:alt-text: A kettle',
				'image:caption: In the brewhouse',
			]);
		});

		it('offers to add a missing alt text and leaves out a missing caption', async () => {
			const { element } = await card({ uid: 'cover' });

			expect(texts(element)).toEqual(['image:alt-text: image:alt-text-add']);
		});

		it('adds the alt text through the details dialog and keeps focus nearby', async () => {
			const { element } = await card({ uid: 'cover' });
			const add = element.querySelector<HTMLButtonElement>('.cms-image-card .add')!;
			add.focus();
			add.click();
			await tick();

			expect(document.activeElement).toBe(field('alt'));

			await enter(field('alt'), 'A kettle');
			await action('common:apply');

			expect(texts(element)).toEqual(['image:alt-text: A kettle']);
			expect(document.activeElement).toBe(element.querySelector('.cms-image-card .edit'));
		});

		it('leaves out a missing alt text when read-only', async () => {
			const { element } = await card({ uid: 'cover' }, { immutable: true });

			expect(texts(element)).toEqual([]);
		});

		it('reads the catalog behind an empty text', async () => {
			const { element } = await media('cosray-image', {
				value: { zxx: [{ uid: 'cover' }] },
				field: { name: 'cover', limit: { min: 0, max: 1 } },
				assets: { cover: { ...assets.cover, meta: { alt: { en: 'A kettle' } } } },
			});

			expect(texts(element)).toEqual(['image:alt-text: A kettle']);
		});

		it('follows the content language', async () => {
			const { element } = await card(
				{ uid: 'cover', meta: { alt: { de: 'Ein Kessel', en: 'A kettle' } } },
				{ translate: true, translateMode: 'symmetric' },
			);

			expect(texts(element)).toEqual(['image:alt-text: Ein Kessel']);

			element.locale = 'en';

			expect(texts(element)).toEqual(['image:alt-text: A kettle']);
		});
	});

	it('falls back to the original when the preview cannot be generated', async () => {
		await details();
		const image = document.querySelector<HTMLImageElement>('.cms-modal .preview img')!;

		image.dispatchEvent(new Event('error'));

		expect(image.getAttribute('src')).toBe(assets.cover.url);
	});

	it('returns focus to the edit button after applying', async () => {
		const { element } = await media('cosray-image', {
			value: { zxx: [{ uid: 'cover' }] },
			field: { name: 'cover', limit: { min: 0, max: 1 } },
			assets,
		});
		const edit = element.querySelector<HTMLButtonElement>('.cms-image-card .edit')!;
		edit.focus();
		edit.click();
		await tick();
		await enter(field('alt'), 'A kettle');
		await action('common:apply');

		expect(document.activeElement).toBe(element.querySelector('.cms-image-card .edit'));
	});

	it('follows the content language and writes to the shown one', async () => {
		const { element, changes } = await details(
			{ uid: 'cover', meta: { alt: { de: 'Ein Kessel', en: 'A kettle' } } },
			{ translate: true, translateMode: 'symmetric' },
		);
		const mirror = document.querySelector<HTMLElement>('.cms-modal .cms-content-locales')!;

		expect(field('alt').value).toBe('Ein Kessel');
		expect(mirror.querySelector('[aria-pressed="true"]')?.textContent?.trim()).toBe('Deutsch');

		element.locale = 'en';
		await tick();
		expect(field('alt').value).toBe('A kettle');
		expect(mirror.querySelector('[aria-pressed="true"]')?.textContent?.trim()).toBe('English');

		await enter(field('alt'), 'A copper kettle');
		await action('common:apply');

		expect(changes).toHaveBeenCalledExactlyOnceWith({
			zxx: [{ uid: 'cover', meta: { alt: { de: 'Ein Kessel', en: 'A copper kettle' } } }],
		});
	});

	it('shows catalog texts as placeholders', async () => {
		await media('cosray-image', {
			value: { zxx: [{ uid: 'cover' }] },
			field: { name: 'cover', limit: { min: 0, max: 1 } },
			assets: { cover: { ...assets.cover, meta: { alt: { en: 'From the catalog' } } } },
		}).then(({ element }) =>
			element.querySelector<HTMLButtonElement>('.cms-image-card .edit')!.click(),
		);
		await tick();
		field('alt').blur();

		expect(field('alt').placeholder).toBe('From the catalog');
	});

	it('swaps in an uploaded image only once applied and without the old texts', async () => {
		vi.spyOn(window.Cosray!, 'upload').mockResolvedValueOnce({
			ok: true,
			uid: 'fresh',
			filename: 'fresh.png',
			url: '/media/fresh.png',
		});
		const { element, changes } = await details({
			uid: 'cover',
			meta: { alt: { zxx: 'The old image' } },
		});
		const picker = document.querySelector<HTMLInputElement>('.cms-modal input[type="file"]')!;
		const click = vi.spyOn(picker, 'click').mockImplementation(() => {});

		document.querySelectorAll<HTMLButtonElement>('.cms-modal .cms-replace-menu button')[1].click();
		expect(click).toHaveBeenCalledOnce();

		Object.defineProperty(picker, 'files', {
			configurable: true,
			value: [new File(['content'], 'fresh.png', { type: 'image/png' })],
		});
		picker.dispatchEvent(new Event('input', { bubbles: true }));

		await vi.waitFor(() => {
			expect(document.querySelector('.cms-modal .preview img')?.getAttribute('src')).toBe(
				'/media/fresh.png',
			);
		});
		expect(field('alt').value).toBe('');
		expect(changes).not.toHaveBeenCalled();
		expect(element.querySelector('.cms-image-card .filename')?.textContent).toBe('cover.jpg');

		await action('common:apply');

		expect(changes).toHaveBeenCalledExactlyOnceWith({ zxx: [{ uid: 'fresh' }] });
		expect(element.querySelector('.cms-image-card .filename')?.textContent).toBe('fresh.png');
	});

	it('keeps the image and says why when an upload fails', async () => {
		vi.spyOn(window.Cosray!, 'upload').mockResolvedValueOnce({
			ok: false,
			filename: 'big.png',
			error: 'Too big',
		});
		await details();
		const picker = document.querySelector<HTMLInputElement>('.cms-modal input[type="file"]')!;

		Object.defineProperty(picker, 'files', {
			configurable: true,
			value: [new File(['content'], 'big.png', { type: 'image/png' })],
		});
		picker.dispatchEvent(new Event('input', { bubbles: true }));

		await vi.waitFor(() => {
			expect(document.querySelector('.cms-dialog-message.is-error')).not.toBeNull();
		});
		expect(document.querySelector('.cms-modal .preview img')?.getAttribute('src')).toBe(
			'/media/cover-preview.jpg',
		);
	});
});

describe('file rows', () => {
	it('takes a value assigned after connecting as the edits without reporting', async () => {
		const { element, changes } = await file();

		element.assets = {
			...element.assets,
			other: { filename: 'other.pdf', url: '/media/other.pdf', kind: 'file' },
		};
		element.value = { zxx: [{ uid: 'other' }] };
		await tick();

		expect(
			Array.from(element.querySelectorAll('.cms-file-row .filename')).map((n) => n.textContent),
		).toEqual(['other.pdf']);
		expect(changes).not.toHaveBeenCalled();
	});

	it('shows each file with its type icon or thumbnail, its link, its title and its size', async () => {
		const { element } = await media('cosray-file', {
			value: {
				zxx: [
					{ uid: 'terms', meta: { title: { zxx: 'Terms of delivery' } } },
					{ uid: 'photo' },
					{ uid: 'notes' },
				],
			},
			field: { name: 'downloads' },
			assets: {
				terms: {
					filename: 'terms.docx',
					url: '/media/terms.docx',
					kind: 'file',
					mime: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					bytes: 46490,
				},
				photo: {
					filename: 'photo.jpg',
					url: '/media/photo.jpg',
					thumbUrl: '/media/photo-thumb.jpg',
					kind: 'image',
					bytes: 862208,
				},
				notes: { filename: 'notes', url: '/media/notes', kind: 'file' },
			},
		});
		const rows = element.querySelectorAll<HTMLElement>('.cms-file-row');

		expect(rows).toHaveLength(3);
		expect(rows[0].querySelector('.icon svg')?.classList.contains('bi-file-earmark-word')).toBe(
			true,
		);
		expect(rows[0].querySelector<HTMLAnchorElement>('a.filename')?.getAttribute('href')).toBe(
			'/media/terms.docx',
		);
		expect(rows[0].querySelector('.title')?.textContent).toBe('Terms of delivery');
		expect(rows[0].querySelector('.size')?.textContent).toBe('45.4 KB');
		expect(rows[1].querySelector('.icon img')?.getAttribute('src')).toBe('/media/photo-thumb.jpg');
		expect(rows[2].querySelector('.icon svg')?.classList.contains('bi-file-earmark')).toBe(true);
		expect(rows[2].querySelector('.title')).toBeNull();
		expect(rows[2].querySelector('.size')?.textContent).toBe('');
	});

	it('persists a reorder of the rows', async () => {
		const { element, changes } = await media('cosray-file', {
			value: { zxx: [{ uid: 'a' }, { uid: 'b' }] },
			field: { name: 'downloads' },
			assets: {
				a: { filename: 'a.pdf', url: '/media/a.pdf', kind: 'file' },
				b: { filename: 'b.pdf', url: '/media/b.pdf', kind: 'file' },
			},
		});
		const list = element.querySelector<HTMLElement>('.cms-file-list')!;
		const sorter = Sortable.get(list)!;

		// jsdom has no drag layout; Sortable moves the DOM before calling onUpdate.
		list.append(list.firstElementChild!);
		sorter.option('onUpdate')!.call(sorter, { oldIndex: 0, newIndex: 1 } as SortableEvent);
		await tick();

		expect(changes).toHaveBeenCalledExactlyOnceWith({ zxx: [{ uid: 'b' }, { uid: 'a' }] });
	});

	it('removes a row and keeps the rest', async () => {
		const { element, changes } = await media('cosray-file', {
			value: { zxx: [{ uid: 'a' }, { uid: 'b' }] },
			field: { name: 'downloads' },
			assets: {
				a: { filename: 'a.pdf', url: '/media/a.pdf', kind: 'file' },
				b: { filename: 'b.pdf', url: '/media/b.pdf', kind: 'file' },
			},
		});

		element.querySelector<HTMLButtonElement>('.cms-file-row .remove')!.click();
		await tick();

		expect(changes).toHaveBeenCalledExactlyOnceWith({ zxx: [{ uid: 'b' }] });
		expect(element.querySelectorAll('.cms-file-row')).toHaveLength(1);
	});
});

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

describe('file dialog focus', () => {
	it('returns to the row after the language changed while the dialog was open', async () => {
		const { element } = await file();
		const pencil = element.querySelector<HTMLButtonElement>('.cms-file-row .edit')!;
		pencil.focus();
		pencil.click();
		await tick();

		element.locale = 'en';
		expect(element.querySelector('.cms-file-row .title')?.textContent).toBe('English title');

		await enter(document.querySelector<HTMLInputElement>('.cms-modal input')!, 'Retitled');
		await action('common:apply');

		expect(element.querySelector('.cms-file-row .title')?.textContent).toBe('Retitled');
		expect(document.activeElement).toBe(element.querySelector('.cms-file-row .edit'));
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
			expect(element.querySelector('.cms-file-row .filename')?.textContent?.trim()).toBe('german');

			element.locale = 'en';
			await tick();
			expect(element.textContent).toContain('uploaded.pdf');
		},
	);

	it('previews a fresh upload from its thumbnail and shows its size', async () => {
		vi.spyOn(window.Cosray!, 'upload').mockResolvedValueOnce({
			ok: true,
			uid: 'fresh',
			filename: 'fresh.png',
			url: '/media/fresh.png',
			thumbUrl: '/media/fresh-thumb.png',
			bytes: 862208,
			width: 2400,
			height: 1600,
		});
		const { element } = await media('cosray-image', {
			value: {},
			field: { name: 'cover', limit: { min: 0, max: 1 } },
		});
		upload(element);

		await vi.waitFor(() => {
			expect(element.querySelector('.cms-image-card img')?.getAttribute('src')).toBe(
				'/media/fresh-thumb.png',
			);
		});
		expect(
			element.querySelector('.cms-image-card .facts')?.textContent?.replace(/\s+/g, ' ').trim(),
		).toBe('2400 × 1600 px · 842.0 KB');
	});

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

describe('gallery details', () => {
	const assets = {
		a: { filename: 'a.jpg', url: '/media/a.jpg', kind: 'image' },
		b: { filename: 'b.jpg', url: '/media/b.jpg', kind: 'image' },
		c: { filename: 'c.jpg', url: '/media/c.jpg', kind: 'image' },
	};

	function gallery(extra: Record<string, unknown> = {}) {
		return media('cosray-image', {
			value: { zxx: [{ uid: 'a' }, { uid: 'b' }, { uid: 'c' }] },
			field: { name: 'gallery', ...extra },
			assets,
		});
	}

	const alt = () => document.querySelector<HTMLTextAreaElement>('.cms-modal textarea[id$="-alt"]')!;
	const shown = () => document.querySelector('.cms-modal .modal-header .filename')?.textContent;

	it('opens an image from its tile, by the button or the picture', async () => {
		const { element } = await gallery();
		const tiles = element.querySelectorAll<HTMLElement>('.tile');

		tiles[1].querySelector<HTMLButtonElement>('.edit')!.click();
		await tick();
		expect(shown()).toBe('b.jpg');
		await action('common:cancel');

		tiles[2].querySelector<HTMLElement>('.picture')!.click();
		await tick();
		expect(shown()).toBe('c.jpg');
	});

	it('opens the image a drag moved', async () => {
		const { element, changes } = await gallery();
		const grid = element.querySelector<HTMLElement>('.tiles')!;
		const sorter = Sortable.get(grid)!;
		const first = grid.firstElementChild as HTMLElement;

		grid.append(first);
		sorter.option('onUpdate')!.call(sorter, { oldIndex: 0, newIndex: 2 } as SortableEvent);
		first.querySelector<HTMLButtonElement>('.edit')!.click();
		await tick();

		expect(changes).toHaveBeenCalledExactlyOnceWith({
			zxx: [{ uid: 'b' }, { uid: 'c' }, { uid: 'a' }],
		});
		expect(shown()).toBe('a.jpg');
	});

	it('removes an image from its tile', async () => {
		const { element, changes } = await gallery();

		element.querySelectorAll<HTMLButtonElement>('.tile .discard')[1].click();
		await tick();

		expect(changes).toHaveBeenCalledExactlyOnceWith({ zxx: [{ uid: 'a' }, { uid: 'c' }] });
		expect(element.querySelectorAll('.tile')).toHaveLength(2);
	});

	it('steps through the images and applies all drafts together', async () => {
		const { element, changes } = await gallery();
		element.querySelector<HTMLButtonElement>('.tile .edit')!.click();
		await tick();
		const next = document.querySelector<HTMLButtonElement>('.cms-modal .step.next')!;
		const place = document.querySelector('.cms-modal .position');

		expect(place?.textContent).toBe('1 / 3');
		await enter(alt(), 'First');

		next.focus();
		next.click();
		expect(shown()).toBe('b.jpg');
		expect(place?.textContent).toBe('2 / 3');
		expect(alt().value).toBe('');
		expect(document.activeElement).toBe(next);
		await enter(alt(), 'Second');

		next.click();
		next.click();
		expect(shown()).toBe('a.jpg');
		expect(alt().value).toBe('First');
		expect(changes).not.toHaveBeenCalled();

		await action('common:apply');

		expect(changes).toHaveBeenCalledExactlyOnceWith({
			zxx: [
				{ uid: 'a', meta: { alt: { zxx: 'First' } } },
				{ uid: 'b', meta: { alt: { zxx: 'Second' } } },
				{ uid: 'c' },
			],
		});
	});

	it('discards the drafts of every image on cancel', async () => {
		const { element, changes } = await gallery();
		element.querySelector<HTMLButtonElement>('.tile .edit')!.click();
		await tick();

		await enter(alt(), 'First');
		document.querySelector<HTMLButtonElement>('.cms-modal .step.prev')!.click();
		expect(shown()).toBe('c.jpg');
		await enter(alt(), 'Last');
		await action('common:cancel');

		expect(changes).not.toHaveBeenCalled();
	});

	it('replaces the shown image only', async () => {
		const { element, changes } = await gallery();
		element.querySelectorAll<HTMLButtonElement>('.tile .edit')[1].click();
		await tick();

		document.querySelector<HTMLButtonElement>('.cms-modal .cms-replace-menu button')!.click();
		await tick();
		expect(ajax).toHaveBeenCalledWith('GET', '/panel/media/picker?kind=image', expect.anything());
		const library = Array.from(document.querySelectorAll<HTMLDialogElement>('dialog')).at(-1)!;
		const tile = document.createElement('button');
		tile.dataset.pick = JSON.stringify({
			uid: 'd',
			filename: 'd.jpg',
			url: '/media/d.jpg',
			thumbUrl: '/media/d.jpg',
			kind: 'image',
		});
		library.querySelector('.modal-body > div')!.append(tile);
		tile.click();
		await tick();

		expect(shown()).toBe('d.jpg');
		expect(changes).not.toHaveBeenCalled();

		await action('common:apply');

		expect(changes).toHaveBeenCalledExactlyOnceWith({
			zxx: [{ uid: 'a' }, { uid: 'd' }, { uid: 'c' }],
		});
		expect(element.querySelectorAll('.tile')[1].getAttribute('title')).toBe('d.jpg');
	});

	it('shows the texts of a read-only gallery without the actions', async () => {
		const { element } = await gallery({ immutable: true });
		const tile = element.querySelector<HTMLElement>('.tile')!;

		expect(tile.querySelector('.discard')).toBeNull();

		tile.querySelector<HTMLButtonElement>('.edit')!.click();
		await tick();

		expect(alt().readOnly).toBe(true);
		expect(document.querySelector('.cms-modal .replace')).toBeNull();
		expect(document.querySelector('.cms-modal .step.next')).not.toBeNull();
	});

	it('hides the stepper of a lone image', async () => {
		const { element } = await media('cosray-image', {
			value: { zxx: [{ uid: 'a' }] },
			field: { name: 'gallery' },
			assets,
		});

		element.querySelector<HTMLButtonElement>('.tile .edit')!.click();
		await tick();

		expect(document.querySelector('.cms-modal .stepper')).toBeNull();
	});
});

describe('per-use meta', () => {
	it('keeps the open video dialog on the language chosen meanwhile', async () => {
		const { element } = await media('cosray-video', {
			value: {
				zxx: [{ uid: 'clip', meta: { caption: { de: 'Ein Clip', en: 'A clip' } } }],
			},
			field: { name: 'clip', translate: true, caption: true, limit: { min: 0, max: 1 } },
			assets: { clip: { filename: 'clip.mp4', url: '/media/clip.mp4', kind: 'video' } },
		});
		element.querySelector<HTMLButtonElement>('.cms-file-row .edit')!.click();
		await tick();
		const mirror = document.querySelector<HTMLElement>('.cms-modal .cms-content-locales')!;

		expect(captionShown(document)).toBe('Ein Clip');
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

		expect(captionShown(document)).toBe('A clip');
		expect(mirror.querySelector('[aria-pressed="true"]')?.textContent?.trim()).toBe('English');
	});

	it('offers no edit for a video field without a caption', async () => {
		const { element } = await media('cosray-video', {
			value: { zxx: [{ uid: 'clip' }] },
			field: { name: 'clip', limit: { min: 0, max: 1 } },
			assets: { clip: { filename: 'clip.mp4', url: '/media/clip.mp4', kind: 'video' } },
		});

		expect(element.querySelector('.cms-file-row .edit')).toBeNull();
	});

	it('edits the caption of a video through its modal', async () => {
		const { element, changes } = await media('cosray-video', {
			value: { zxx: [{ uid: 'clip' }] },
			field: { name: 'clip', caption: true, limit: { min: 0, max: 1 } },
			assets: { clip: { filename: 'clip.mp4', url: '/media/clip.mp4', kind: 'video' } },
		});
		expect(element.querySelector('.cms-video video')?.getAttribute('src')).toBe('/media/clip.mp4');
		element.querySelector<HTMLButtonElement>('.cms-file-row .edit')!.click();
		await tick();
		expect(document.querySelector('.cms-modal [id$="-caption"]')).not.toBeNull();
		expect(document.querySelector('.cms-modal [id$="-alt"], .cms-modal [id$="-title"]')).toBeNull();

		writeCaption(document, 'A short clip');
		await action('common:apply');

		expect(changes).toHaveBeenCalledExactlyOnceWith({
			zxx: [{ uid: 'clip', meta: { caption: { zxx: captionDoc('A short clip') } } }],
		});
	});
});

describe('block presentation', () => {
	const cover = { filename: 'cover.jpg', url: '/media/cover.jpg', kind: 'image' };

	async function figure(settings?: HTMLElement, item: FileItem = { uid: 'cover' }) {
		return media('cosray-image', {
			value: { zxx: [item] },
			field: { name: 'image', limit: { min: 0, max: 1 }, presentation: 'block', caption: true },
			assets: { cover },
			...(settings ? { settings } : {}),
		} as HostPayload);
	}

	it('renders an image as a figure with its caption and edits it in the details', async () => {
		const { element, changes } = await figure(undefined, {
			uid: 'cover',
			meta: { caption: { zxx: 'Under the image' } },
		});

		expect(element.querySelector('.cms-image-card')).toBeNull();
		expect(element.querySelector('.cms-media-meta')).toBeNull();
		expect(element.querySelector('.cms-image-figure img')?.getAttribute('src')).toBe(
			'/media/cover.jpg',
		);
		expect(element.querySelector('.cms-image-figure figcaption')?.textContent).toBe(
			'Under the image',
		);

		element.querySelector<HTMLButtonElement>('.cms-image-figure .edit')!.click();
		await tick();
		writeCaption(document, 'Changed');
		await action('common:apply');

		expect(changes).toHaveBeenCalledExactlyOnceWith({
			zxx: [{ uid: 'cover', meta: { caption: { zxx: captionDoc('Changed') } } }],
		});
		expect(element.querySelector('.cms-image-figure figcaption')?.textContent).toBe('Changed');
	});

	it("shows a neutral image's catalog texts in the site's default locale", async () => {
		const { element } = await media('cosray-image', {
			value: { zxx: [{ uid: 'cover' }] },
			field: { name: 'image', limit: { min: 0, max: 1 }, presentation: 'block', caption: true },
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

		element.querySelector<HTMLButtonElement>('.cms-image-figure .edit')!.click();
		await tick();
		const alt = document.querySelector<HTMLTextAreaElement>('.cms-modal textarea[id$="-alt"]')!;
		alt.blur();

		expect(alt.placeholder).toBe('A kettle');
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

	it('leaves the settings slot alone', async () => {
		const slot = document.createElement('div');
		document.body.append(slot);
		await figure(slot);

		expect(slot.childElementCount).toBe(0);
	});

	it('replaces and removes the image', async () => {
		vi.spyOn(window.Cosray!, 'upload').mockResolvedValueOnce({
			ok: true,
			uid: 'fresh',
			filename: 'fresh.png',
			url: '/media/fresh.png',
		});
		const { element, changes } = await figure(undefined, {
			uid: 'cover',
			meta: { alt: { zxx: 'Old alt' } },
		});

		upload(element);
		await vi.waitFor(() => {
			expect(element.querySelector('.cms-image-figure img')?.getAttribute('src')).toBe(
				'/media/fresh.png',
			);
		});
		expect(changes).toHaveBeenLastCalledWith({ zxx: [{ uid: 'fresh' }] });

		element.querySelector<HTMLButtonElement>('.cms-image-figure .discard')!.click();
		await tick();

		expect(changes).toHaveBeenLastCalledWith({ zxx: [] });
		expect(element.querySelector('.cms-image-figure')).toBeNull();
		expect(element.querySelector('.cms-media-field > .bar')).not.toBeNull();
	});

	it('renders a video as a player with its caption form in the slot', async () => {
		const slot = document.createElement('div');
		document.body.append(slot);
		const { element } = await media('cosray-video', {
			value: { zxx: [{ uid: 'clip' }] },
			field: { name: 'video', limit: { min: 0, max: 1 }, presentation: 'block', caption: true },
			assets: { clip: { filename: 'clip.mp4', url: '/media/clip.mp4', kind: 'video' } },
			settings: slot,
		} as HostPayload);

		expect(element.querySelector('.cms-video-figure video')?.getAttribute('src')).toBe(
			'/media/clip.mp4',
		);
		expect(slot.querySelector('[id$="-caption"]')).not.toBeNull();
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

	it('shows the tiles alone and keeps the settings in the slot', async () => {
		const slot = document.createElement('div');
		const { element } = await gallery(slot, { ratio: { zxx: '4/3' } });
		const tiles = element.querySelector<HTMLElement>('.cms-gallery.is-block .tiles')!;

		expect(tiles.querySelectorAll('.tile')).toHaveLength(2);
		expect(tiles.style.getPropertyValue('--ratio')).toBe('4/3');
		expect(element.querySelector('.cms-media-meta')).toBeNull();
		expect(slot.querySelector<HTMLSelectElement>('select')!.value).toBe('4/3');
		expect(slot.querySelector('.cms-media-meta')).toBeNull();
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

	it('edits an image in the same details as any other', async () => {
		const slot = document.createElement('div');
		const { element, changes } = await gallery(slot);

		element.querySelectorAll<HTMLButtonElement>('.tile .edit')[1].click();
		await tick();
		await enter(
			document.querySelector<HTMLTextAreaElement>('.cms-modal textarea[id$="-alt"]')!,
			'Second',
		);
		await action('common:apply');

		expect(changes).toHaveBeenLastCalledWith({
			zxx: [{ uid: 'a' }, { uid: 'b', meta: { alt: { zxx: 'Second' } } }],
		});
	});
});
