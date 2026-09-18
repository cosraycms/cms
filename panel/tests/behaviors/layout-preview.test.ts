import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/layout-preview';
import { closeDialog } from '../../src/lib/dialogs';

let uninstall: (() => void) | null = null;

beforeEach(() => {
	uninstall = install();
	localStorage.clear();
});

afterEach(() => {
	for (const dialog of document.querySelectorAll('dialog')) closeDialog(dialog);
	uninstall?.();
	uninstall = null;
	vi.unstubAllGlobals();
	document.body.innerHTML = '';
	delete window.Cosray;
});

function page(locale = 'de'): void {
	document.body.innerHTML = `
		<form id="node-editor-form" data-content-locale-scope data-content-locale="${locale}">
			<div class="cms-field" data-field="content">
				<label class="label">
					<div>Content</div>
					<button type="button" class="meta-button layout-preview" data-layout-preview="content">Layout preview</button>
				</label>
				<div class="cms-blocks-editor"><div class="grid"><div class="block"></div></div></div>
				<input name="content[content][value][zxx][0][uid]" value="abc" />
				<input name="content[content][value][zxx][0][fields][text][value][zxx]" value="Hello" />
				<input type="file" name="upload" />
			</div>
			<input type="hidden" name="_complete" value="1" />
		</form>
		<dialog class="cms-modal cms-layout-preview" data-layout-preview-dialog
			data-url="/cp/node/node-1/blocks" data-error="Preview failed">
			<header class="modal-header">
				<h2 class="modal-title" data-dialog-title>Layout preview</h2>
				<div class="widths" role="group">
					<button type="button" class="option" aria-pressed="false" data-layout-preview-width="1200">Desktop</button>
					<button type="button" class="option" aria-pressed="false" data-layout-preview-width="1024" data-layout-preview-height="768">Tablet landscape</button>
					<button type="button" class="option" aria-pressed="false" data-layout-preview-width="768" data-layout-preview-height="1024">Tablet portrait</button>
					<button type="button" class="option" aria-pressed="false" data-layout-preview-width="390" data-layout-preview-height="844">Smartphone</button>
				</div>
				<button type="button" data-layout-preview-reload>Reload</button>
				<button type="button" data-dialog-close>Close</button>
			</header>
			<div class="modal-body">
				<div class="stage" data-layout-preview-stage>
					<iframe class="frame" sandbox="allow-same-origin" data-layout-preview-frame></iframe>
				</div>
			</div>
		</dialog>
	`;
}

function button(): HTMLElement {
	return document.querySelector<HTMLElement>('[data-layout-preview]')!;
}

function dialog(): HTMLDialogElement {
	return document.querySelector<HTMLDialogElement>('[data-layout-preview-dialog]')!;
}

function frame(): HTMLIFrameElement {
	return document.querySelector<HTMLIFrameElement>('[data-layout-preview-frame]')!;
}

function preset(width: number): HTMLElement {
	return document.querySelector<HTMLElement>(`[data-layout-preview-width="${width}"]`)!;
}

function respond(
	html = '<!doctype html><html><body>sheet</body></html>',
	ok = true,
): ReturnType<typeof vi.fn> {
	const fetchMock = vi.fn().mockResolvedValue({ ok, text: () => Promise.resolve(html) });
	vi.stubGlobal('fetch', fetchMock);

	return fetchMock;
}

describe('layout preview', () => {
	it('posts the form as JSON for the selected content language and opens the frame', async () => {
		page();
		const fetchMock = respond();

		button().click();
		await vi.waitFor(() => expect(dialog().open).toBe(true));

		expect(fetchMock).toHaveBeenCalledTimes(1);
		const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
		expect(url).toBe('/cp/node/node-1/blocks/content?locale=de');
		expect(init.method).toBe('POST');
		expect((init.headers as Record<string, string>)['Content-Type']).toBe('application/json');
		// The file input stays out; the rest nests like the save body, with
		// indexes as keys the way the transport sends them.
		expect(JSON.parse(init.body as string)).toEqual({
			content: {
				content: {
					value: { zxx: { '0': { uid: 'abc', fields: { text: { value: { zxx: 'Hello' } } } } } },
				},
			},
			_complete: '1',
		});
		expect(frame().getAttribute('srcdoc')).toContain('sheet');
		expect(preset(1200).getAttribute('aria-pressed')).toBe('true');
		expect(frame().style.width).toBe('1200px');
	});

	it.each(['56.25%', '177.78%'])(
		'shows the YouTube thumbnail in the rendered video space (%s)',
		async (ratio) => {
			page();
			respond(`<!doctype html><html lang="de"><head><style>body { margin: 0; }</style></head><body>
				<h2>Video</h2>
				<div class="youtube-container"><div style="position: relative; padding-top: ${ratio}">
					<iframe class="youtube" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%"
						src="https://www.youtube.com/embed/dQw4w9WgXcQ" allowfullscreen></iframe>
				</div></div>
				<iframe src="https://example.com/embed/other"></iframe>
			</body></html>`);

			button().click();
			await vi.waitFor(() => expect(dialog().open).toBe(true));

			const sheet = new DOMParser().parseFromString(frame().srcdoc, 'text/html');
			const image = sheet.querySelector<HTMLImageElement>('.youtube-container img')!;
			expect(image.src).toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
			expect(image.alt).toBe('YouTube');
			expect(image.parentElement!.style.paddingTop).toBe(ratio);
			expect(image.style.position).toBe('absolute');
			expect(image.style.width).toBe('100%');
			expect(image.style.height).toBe('100%');
			expect(image.style.objectFit).toBe('cover');
			expect(sheet.doctype?.name).toBe('html');
			expect(sheet.documentElement.lang).toBe('de');
			expect(sheet.querySelector('style')!.textContent).toBe('body { margin: 0; }');
			expect(sheet.querySelector('h2')!.textContent).toBe('Video');
			expect(sheet.querySelector('iframe')!.src).toBe('https://example.com/embed/other');
		},
	);

	it('keeps incomplete YouTube ids from becoming thumbnail requests', async () => {
		page();
		respond('<iframe class="youtube" src="https://www.youtube.com/embed/unfinished"></iframe>');

		button().click();
		await vi.waitFor(() => expect(dialog().open).toBe(true));

		const sheet = new DOMParser().parseFromString(frame().srcdoc, 'text/html');
		expect(sheet.querySelector('iframe')!.src).toBe('https://www.youtube.com/embed/unfinished');
	});

	it('leaves the locale out when the form has none', async () => {
		page('');
		const fetchMock = respond();

		button().click();
		await vi.waitFor(() => expect(dialog().open).toBe(true));

		expect(fetchMock.mock.calls[0][0]).toBe('/cp/node/node-1/blocks/content');
	});

	it('preserves the creation parent when appending the field and content language', async () => {
		page();
		dialog().dataset.url = '/cp/node/create/page/blocks?parent=parent-node';
		const fetchMock = respond();

		button().click();
		await vi.waitFor(() => expect(dialog().open).toBe(true));

		const url = new URL(fetchMock.mock.calls[0][0], location.href);
		expect(url.pathname).toBe('/cp/node/create/page/blocks/content');
		expect(url.searchParams.get('parent')).toBe('parent-node');
		expect(url.searchParams.get('locale')).toBe('de');
	});

	it('changes the frame width with a preset and remembers it for the next opening', async () => {
		page();
		respond();

		button().click();
		await vi.waitFor(() => expect(dialog().open).toBe(true));

		preset(390).click();
		expect(preset(390).getAttribute('aria-pressed')).toBe('true');
		expect(preset(1200).getAttribute('aria-pressed')).toBe('false');
		// A device preset gives the frame the device's screen; the desktop fills the stage.
		expect(frame().style.width).toBe('390px');
		expect(frame().style.height).toBe('844px');
		expect(localStorage.getItem('cosray:layout-preview-width')).toBe('390');

		preset(1200).click();
		expect(frame().style.width).toBe('1200px');
		expect(frame().style.height).toBe('');
		preset(390).click();

		closeDialog(dialog());
		await vi.waitFor(() => expect(dialog().open).toBe(false));
		button().click();
		await vi.waitFor(() => expect(dialog().open).toBe(true));

		expect(preset(390).getAttribute('aria-pressed')).toBe('true');
		expect(frame().style.width).toBe('390px');
	});

	it('reloads the frame from the current form', async () => {
		page();
		const fetchMock = respond();

		button().click();
		await vi.waitFor(() => expect(dialog().open).toBe(true));
		document.querySelector<HTMLInputElement>('input[value="Hello"]')!.value = 'Changed';
		document.querySelector<HTMLElement>('[data-layout-preview-reload]')!.click();
		await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));

		const body = JSON.parse((fetchMock.mock.calls[1] as [string, RequestInit])[1].body as string);
		expect(body.content.content.value.zxx[0].fields.text.value.zxx).toBe('Changed');
	});

	it('reports a failed request as a toast and keeps the dialog closed', async () => {
		page();
		respond('', false);
		const error = vi.fn();
		window.Cosray = { toast: { error, success: vi.fn() } } as unknown as typeof window.Cosray;

		button().click();
		await vi.waitFor(() => expect(error).toHaveBeenCalledWith('Preview failed'));

		expect(dialog().open).toBe(false);
	});
});
