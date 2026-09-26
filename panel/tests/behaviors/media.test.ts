import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { CosrayBridge, UploadResult } from '../../src/types/bridge';
import { install } from '../../src/behaviors/media.js';

vi.mock('../../src/lib/locale.js', () => ({ __: (id: string) => id }));

let uninstall: () => void;
const ajax =
	vi.fn<(verb: string, path: string, context?: Record<string, unknown>) => Promise<void>>();
const upload = vi.fn<CosrayBridge['upload']>();
const toastError = vi.fn<(message: string) => void>();

function screen(detail = ''): HTMLElement {
	document.body.innerHTML = `
		<div data-media>
			<button type="button" data-media-upload><span data-media-upload-label>Upload</span></button>
			<input type="file" multiple hidden data-media-upload-input />
			<section class="cms-dropzone" data-media-drop>
				<div data-media-grid>
					<a data-media-tile="a" class="cms-asset-tile active" href="?file=a"></a>
					<a data-media-tile="b" class="cms-asset-tile" href="?file=b"></a>
				</div>
			</section>
			<div id="media-detail" data-media-detail="${detail}">
				<form>
					<button type="button" data-media-focal></button>
					<span data-media-focal-text data-label="Focus" data-empty="No focus">No focus</span>
					<button type="button" hidden data-media-focal-clear></button>
					<span hidden data-media-focal-marker></span>
					<input type="hidden" data-media-focal-x />
					<input type="hidden" data-media-focal-y />
					<button type="button" data-media-delete>Delete</button>
				</form>
				<dialog data-media-delete-dialog><button type="button" data-dialog-close>Cancel</button></dialog>
			</div>
		</div>`;

	return document.querySelector('[data-media]')!;
}

function files(...names: string[]): File[] {
	return names.map(
		(name) =>
			new File(['x'], name, { type: name.endsWith('.png') ? 'image/png' : 'application/pdf' }),
	);
}

function drop(zone: Element, type: string, list: File[] = files('a.png')): void {
	const event = new Event(type, { bubbles: true, cancelable: true });
	Object.defineProperty(event, 'dataTransfer', { value: { types: ['Files'], files: list } });
	zone.dispatchEvent(event);
}

beforeEach(() => {
	window.Cosray = {
		upload,
		toast: { success: vi.fn(), error: toastError },
	} as unknown as CosrayBridge;
	vi.stubGlobal('htmx', { ajax });
	ajax.mockResolvedValue();
	history.replaceState(null, '', '/cp/media?kind=image&page=3');
	uninstall = install();
});

afterEach(() => {
	uninstall();
	vi.unstubAllGlobals();
	vi.clearAllMocks();
	delete window.Cosray;
	document.body.innerHTML = '';
});

describe('media tiles', () => {
	it('marks the tile of the detail htmx swapped in', () => {
		screen('b');

		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(document.querySelector('[data-media-tile="a"]')!.classList.contains('active')).toBe(
			false,
		);
		expect(document.querySelector('[data-media-tile="b"]')!.classList.contains('active')).toBe(
			true,
		);
	});
});

describe('focal point', () => {
	it('takes a click position and clears again', () => {
		screen('a');
		const preview = document.querySelector<HTMLElement>('[data-media-focal]')!;
		preview.getBoundingClientRect = () => new DOMRect(10, 20, 200, 100);

		preview.dispatchEvent(
			new MouseEvent('click', { bubbles: true, detail: 1, clientX: 60, clientY: 95 }),
		);

		expect(document.querySelector<HTMLInputElement>('[data-media-focal-x]')!.value).toBe('0.25');
		expect(document.querySelector<HTMLInputElement>('[data-media-focal-y]')!.value).toBe('0.75');
		expect(document.querySelector<HTMLElement>('[data-media-focal-marker]')!.style.left).toBe(
			'25%',
		);
		expect(document.querySelector('[data-media-focal-text]')!.textContent).toBe('Focus: 25% / 75%');

		document.querySelector<HTMLElement>('[data-media-focal-clear]')!.click();

		expect(document.querySelector<HTMLInputElement>('[data-media-focal-x]')!.value).toBe('');
		expect(document.querySelector<HTMLElement>('[data-media-focal-marker]')!.hidden).toBe(true);
		expect(document.querySelector('[data-media-focal-text]')!.textContent).toBe('No focus');
	});

	it('ignores keyboard activation, which has no position', () => {
		screen('a');

		document.querySelector<HTMLElement>('[data-media-focal]')!.click();

		expect(document.querySelector<HTMLInputElement>('[data-media-focal-x]')!.value).toBe('');
	});
});

describe('delete', () => {
	it('asks in the detail dialog first', () => {
		screen('a');

		document.querySelector<HTMLElement>('[data-media-delete]')!.click();

		expect(document.querySelector<HTMLDialogElement>('[data-media-delete-dialog]')!.open).toBe(
			true,
		);
	});
});

describe('uploads', () => {
	it('uploads one file after another and reloads the listing with the last one selected', async () => {
		const root = screen();
		let resolve!: (result: UploadResult) => void;
		upload
			.mockImplementationOnce(() => new Promise((done) => (resolve = done)))
			.mockResolvedValueOnce({ ok: false, error: 'Too large' })
			.mockResolvedValueOnce({ ok: true, uid: 'third' });
		const input = root.querySelector<HTMLInputElement>('[data-media-upload-input]')!;
		Object.defineProperty(input, 'files', { value: files('one.png', 'two.pdf', 'three.png') });

		input.dispatchEvent(new Event('change', { bubbles: true }));

		expect(upload).toHaveBeenCalledTimes(1);
		expect(upload).toHaveBeenLastCalledWith('image', expect.any(File));
		expect(root.querySelector('[data-media-upload]')!.hasAttribute('disabled')).toBe(true);
		expect(root.querySelector('[data-media-upload-label]')!.textContent).toBe(
			'upload:in-progress 0/3',
		);

		resolve({ ok: true, uid: 'first' });
		await vi.waitFor(() => expect(ajax).toHaveBeenCalled());

		expect(upload).toHaveBeenNthCalledWith(2, 'file', expect.any(File));
		expect(toastError).toHaveBeenCalledWith('two.pdf: Too large');
		expect(ajax).toHaveBeenCalledWith('GET', '/cp/media?kind=image&file=third', {
			target: '#main',
			replace: 'true',
		});
		expect(root.querySelector('[data-media-upload]')!.hasAttribute('disabled')).toBe(false);
		expect(root.querySelector('[data-media-upload-label]')!.textContent).toBe('Upload');
	});

	it('leaves the listing alone when nothing arrived', async () => {
		const root = screen();
		upload.mockResolvedValue({ ok: false });
		const input = root.querySelector<HTMLInputElement>('[data-media-upload-input]')!;
		Object.defineProperty(input, 'files', { value: files('broken.png') });

		input.dispatchEvent(new Event('change', { bubbles: true }));
		await vi.waitFor(() => expect(toastError).toHaveBeenCalledWith('broken.png: upload:failed'));

		expect(ajax).not.toHaveBeenCalled();
	});

	it('shows the drop state while files hover and uploads what drops', async () => {
		screen();
		upload.mockResolvedValue({ ok: true, uid: 'dropped' });
		const zone = document.querySelector('[data-media-drop]')!;
		const grid = document.querySelector('[data-media-grid]')!;

		drop(zone, 'dragenter');
		drop(grid, 'dragenter');
		drop(grid, 'dragleave');
		expect(zone.classList.contains('is-dragging')).toBe(true);
		drop(zone, 'dragleave');
		expect(zone.classList.contains('is-dragging')).toBe(false);

		drop(zone, 'dragenter');
		drop(grid, 'drop', files('dropped.png'));

		expect(zone.classList.contains('is-dragging')).toBe(false);
		await vi.waitFor(() =>
			expect(ajax).toHaveBeenCalledWith(
				'GET',
				'/cp/media?kind=image&file=dropped',
				expect.anything(),
			),
		);
	});
});
