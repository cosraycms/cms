import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { BridgeSystem } from '../../src/lib/bridge';
import type { HostPayload } from '../../src/lib/host';
import { installBridge } from '../../src/lib/bridge-standalone';
import '../../src/elements/link.js';

vi.mock('../../src/lib/locale.js', () => ({
	__: (id: string, args?: Record<string, string>) =>
		args ? `${id} ${Object.values(args).join(' ')}` : id,
	__n: (one: string, many: string, n: number) => (n === 1 ? one : many),
}));

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

const locales = { default: 'en', all: system.locales };

type LinkElement = HTMLElement & HostPayload & { locale: string };

beforeEach(() => {
	installBridge(system);
});

afterEach(async () => {
	document.querySelector<HTMLButtonElement>('.cms-modal [data-dialog-close]')?.click();
	document.body.replaceChildren();
	await tick();
	delete window.Cosray;
});

function link(payload: Partial<HostPayload>, locale = 'zxx') {
	const element = document.createElement('cosray-link') as LinkElement;
	Object.assign(element, { field: { label: 'Target' }, locales, ...payload });
	element.locale = locale;
	const changes = vi.fn<(value: unknown) => void>();
	element.addEventListener('cosray-change', (event) => {
		changes(JSON.parse(JSON.stringify((event as CustomEvent).detail.value)));
	});
	document.body.append(element);

	return { element, changes };
}

function button(element: HTMLElement, label: string): HTMLButtonElement {
	return [...element.querySelectorAll<HTMLButtonElement>('button')].find(
		(entry) => entry.textContent === label || entry.title === label,
	)!;
}

async function enterAddress(address: string, blank = false): Promise<void> {
	await tick();
	const input = document.querySelector<HTMLInputElement>('.cms-link-dialog-url input')!;
	input.value = address;
	input.dispatchEvent(new Event('input', { bubbles: true }));

	if (blank) {
		document.querySelector<HTMLInputElement>('.cms-modal input[type="checkbox"]')!.click();
	}

	[...document.querySelectorAll<HTMLButtonElement>('.cms-modal button')]
		.find((entry) => entry.textContent === 'link:add')!
		.click();
	await tick();
}

describe('link field', () => {
	it('labels its group with the field', () => {
		const { element } = link({ value: { zxx: null } });

		expect(element.querySelector('[role="group"]')?.getAttribute('aria-label')).toBe('Target');
	});

	it('stores a chosen address for every language when neutral', async () => {
		const { element, changes } = link({ value: { zxx: null } });

		button(element, 'link:add').click();
		await enterAddress('https://example.com', true);

		expect(changes).toHaveBeenLastCalledWith({
			zxx: { href: 'https://example.com', target: '_blank' },
		});
		expect(element.querySelector('.cms-link-text')?.textContent).toBe('https://example.com');
		expect(document.activeElement).toBe(button(element, 'common:edit'));
	});

	it('names a linked file and removes the link', () => {
		const { element, changes } = link({
			value: { zxx: { asset: 'doc1' } },
			assets: { doc1: { filename: 'plan.pdf', url: '/assets/plan.pdf', kind: 'file' } },
		});

		expect(element.querySelector('.cms-link-text')?.textContent).toBe('plan.pdf');

		button(element, 'common:remove').click();

		expect(changes).toHaveBeenLastCalledWith({ zxx: null });
		expect(button(element, 'link:add')).toBeDefined();
	});

	it('offers no changes when read-only', () => {
		const { element } = link({
			value: { zxx: { href: '/about' } },
			field: { label: 'Target', immutable: true },
		});

		expect(element.querySelector('.cms-link-text')?.textContent).toBe('/about');
		expect(element.querySelectorAll('button')).toHaveLength(0);
	});

	it('edits the shown language of a translated link and shows what it falls back to', async () => {
		const { element, changes } = link(
			{
				value: { en: { href: '/en.pdf' }, de: null },
				field: { label: 'Target', translate: true },
			},
			'de',
		);

		expect(element.querySelector('.fallback')?.textContent).toBe(
			'field:fallback-from English:/en.pdf',
		);

		button(element, 'link:add').click();
		await enterAddress('/de.pdf');

		expect(changes).toHaveBeenLastCalledWith({ en: { href: '/en.pdf' }, de: { href: '/de.pdf' } });
		expect(element.querySelector('.fallback')).toBeNull();

		element.locale = 'en';

		expect(element.querySelector('.cms-link-text')?.textContent).toBe('/en.pdf');
	});
});
