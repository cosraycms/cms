import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('$lib/locale', () => ({
	__: (id: string) => `translated:${id}`,
}));

import type { BridgeSystem, CosrayBridge } from '../../src/lib/bridge';
import { installBridge } from '../../src/lib/bridge-standalone';

const system: BridgeSystem = {
	locale: 'en',
	defaultLocale: 'en',
	locales: [{ id: 'en', title: 'English' }],
	customLocales: [],
	prefix: '/cp',
	assets: '/assets',
	debug: false,
	allowedFiles: { file: [], image: ['image/jpeg'], video: [] },
};

beforeEach(() => {
	installBridge(system);
});

afterEach(() => {
	delete window.Cosray;
	document.body.replaceChildren();
	vi.useRealTimers();
	vi.restoreAllMocks();
	vi.unstubAllGlobals();
});

function bridge(): CosrayBridge {
	return window.Cosray!;
}

describe('standalone bridge', () => {
	it('installs the versioned API with its server system payload', () => {
		expect(bridge().version).toBe(1);
		expect(bridge().system()).toBe(system);
	});

	it('keeps an existing version one bridge', () => {
		const existing = { version: 1 } as CosrayBridge;
		window.Cosray = existing;

		installBridge({ ...system, prefix: '/other' });

		expect(window.Cosray).toBe(existing);
	});

	it('uploads through the panel media endpoint', async () => {
		const result = { ok: true, uid: 'image-a', filename: 'image.jpg', url: '/media/image.jpg' };
		const fetchMock = vi.fn().mockResolvedValue({ json: () => Promise.resolve(result) });
		vi.stubGlobal('fetch', fetchMock);
		const file = new File(['image'], 'image.jpg', { type: 'image/jpeg' });

		await expect(bridge().upload('image', file)).resolves.toEqual(result);

		expect(fetchMock).toHaveBeenCalledOnce();
		const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit];
		expect(url).toBe('/cp/media/image');
		expect(options).toMatchObject({
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'xmlhttprequest', Accept: 'application/json' },
		});
		expect(options.body).toBeInstanceOf(FormData);
		expect((options.body as FormData).get('file')).toBe(file);
	});

	it('returns a localized failure when upload transport fails', async () => {
		vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('offline')));
		const file = new File([], 'image.jpg');

		await expect(bridge().upload('image', file)).resolves.toEqual({
			ok: false,
			error: 'translated:upload:failed',
		});
	});

	it('opens a modal and cleans it up from either close path', () => {
		const cleanup = vi.fn();
		const render = vi.fn((host: HTMLElement) => {
			host.textContent = 'Control';

			return cleanup;
		});
		const modal = bridge().modal.open(render);
		const overlay = document.querySelector<HTMLElement>('.cms-modal')!;

		expect(render).toHaveBeenCalledWith(overlay.querySelector('.element'));
		expect(overlay.textContent).toContain('Control');
		expect(overlay.querySelector<HTMLButtonElement>('button.close')?.ariaLabel).toBe(
			'translated:common:close',
		);

		overlay.querySelector<HTMLButtonElement>('button.close')!.click();
		expect(cleanup).toHaveBeenCalledOnce();
		expect(overlay.isConnected).toBe(false);

		const second = bridge().modal.open(() => cleanup, { hideClose: true });
		expect(document.querySelector('.cms-modal button.close')).toBeNull();
		second.close();
		expect(cleanup).toHaveBeenCalledTimes(2);
	});

	it('stacks dismissible toasts and expires them by severity', () => {
		vi.useFakeTimers();

		bridge().toast.success('Saved');
		bridge().toast.error('Failed');
		const items = document.querySelectorAll<HTMLButtonElement>('.cms-toasts .toast');

		expect(items).toHaveLength(2);
		expect(items[0].classList.contains('is-error')).toBe(true);
		expect(items[0].textContent).toBe('Failed');
		expect(items[1].classList.contains('is-success')).toBe(true);

		vi.advanceTimersByTime(3000);
		expect(document.querySelector('.toast.is-success')).toBeNull();
		expect(document.querySelector('.toast.is-error')).not.toBeNull();

		document.querySelector<HTMLButtonElement>('.toast.is-error')!.click();
		expect(document.querySelector('.toast.is-error')).toBeNull();
	});
});
