import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { browseLibrary } from '../../src/lib/library-browser.js';

vi.mock('../../src/lib/runtime.js', () => ({ panelBase: () => '/cp/' }));

const ajax = vi.fn<(verb: string, path: string, context: { target: Element }) => Promise<void>>();

function tile(uid: string, active = false): string {
	const item = JSON.stringify({
		uid,
		filename: `${uid}.png`,
		url: `/assets/${uid}.png`,
		kind: 'image',
	});

	return `<button type="button" class="cms-asset-tile${active ? ' active' : ''}" data-pick='${item}'></button>`;
}

beforeEach(() => {
	ajax.mockImplementation(async (_verb, _path, { target }) => {
		target.innerHTML = `<div data-media-picker>${tile('a', true)}${tile('b')}</div>`;
	});
	vi.stubGlobal('htmx', { ajax });
});

afterEach(() => {
	vi.unstubAllGlobals();
	document.body.replaceChildren();
});

describe('library browser', () => {
	it('loads the picker narrowed to the kind with the current pick marked', () => {
		const container = document.createElement('div');

		browseLibrary(container, { kind: 'image', selected: 'a', pick: vi.fn() });

		expect(ajax).toHaveBeenCalledWith('GET', '/cp/media/picker?kind=image&file=a', {
			target: container,
			swap: 'innerHTML',
		});
	});

	it('browses the whole pool for files', () => {
		browseLibrary(document.createElement('div'), { kind: 'file', pick: vi.fn() });

		expect(ajax).toHaveBeenLastCalledWith('GET', '/cp/media/picker', expect.anything());
	});

	it('reports the picked asset and moves the mark until stopped', async () => {
		const container = document.createElement('div');
		document.body.append(container);
		const pick = vi.fn();

		const stop = browseLibrary(container, { kind: 'image', selected: 'a', pick });
		await Promise.resolve();
		container.querySelectorAll<HTMLElement>('[data-pick]')[1].click();

		expect(pick).toHaveBeenCalledWith(expect.objectContaining({ uid: 'b', filename: 'b.png' }));
		const marks = [...container.querySelectorAll('[data-pick]')].map((t) =>
			t.classList.contains('active'),
		);
		expect(marks).toEqual([false, true]);

		stop();
		container.querySelector<HTMLElement>('[data-pick]')!.click();
		expect(pick).toHaveBeenCalledOnce();
	});
});
