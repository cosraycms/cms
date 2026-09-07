import { tick } from 'svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

import type { RichtextEnvelope } from '../../src/components/richtext/format';
import type { HostPayload } from '../../src/lib/host';
import type { LocaleMap, RichtextDoc } from '../../src/types/data';
import '../../src/elements/richtext/RichTextElement.svelte';

vi.mock('$lib/locale', () => ({ __: (id: string) => id }));

type RichtextElement = HTMLElement & HostPayload & { locale: string };

function doc(text: string): RichtextDoc {
	return {
		type: 'doc',
		content: [
			{ type: 'paragraph', attrs: { class: 'default' }, content: [{ type: 'text', text }] },
		],
	};
}

async function richtext(value: LocaleMap<RichtextDoc | string | null>, format = 'cosray-richtext') {
	const element = document.createElement('cosray-richtext') as RichtextElement;
	Object.assign(element, {
		value,
		format,
		field: { name: 'body', translate: true, tools: ['source'] },
		locale: 'de',
		locales: {
			default: 'en',
			all: [
				{ id: 'en', title: 'English' },
				{ id: 'de', title: 'Deutsch', fallback: 'en' },
			],
		},
	});
	const changes = vi.fn<(detail: RichtextEnvelope) => void>();
	element.addEventListener('cosray-change', (event) => {
		changes(JSON.parse(JSON.stringify((event as CustomEvent).detail)));
	});
	document.body.append(element);
	await tick();

	return { element, changes };
}

async function editSource(element: HTMLElement, html: string): Promise<void> {
	const button = Array.from(element.querySelectorAll('button')).find(
		(button) => button.textContent?.trim() === 'richtext:show-source',
	);
	expect(button).toBeDefined();
	button!.click();
	await tick();
	const input = element.querySelector('textarea')!;
	input.value = html;
	input.dispatchEvent(new Event('input', { bubbles: true }));
	input.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
	await tick();
}

afterEach(async () => {
	document.body.replaceChildren();
	await tick();
});

describe('richtext fallback previews', () => {
	it('keeps the target empty when focusing and switching away from shared content', async () => {
		const { element, changes } = await richtext({ zxx: doc('Shared text'), en: null, de: null });

		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain('Shared text');
		const editor = element.querySelector<HTMLElement>('[contenteditable="true"]')!;
		expect(editor.textContent).toBe('');
		editor.focus();
		await tick();
		expect(element.querySelector('.cms-richtext-fallback')).toBeNull();
		expect(editor.textContent).toBe('');
		editor.blur();
		await tick();
		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain('Shared text');

		element.locale = 'en';
		await tick();
		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain('Shared text');
		expect(element.querySelector('[contenteditable="true"]')?.textContent).toBe('');
		expect(changes).not.toHaveBeenCalled();
		expect(element.value).toEqual({ zxx: doc('Shared text'), en: null, de: null });
	});

	it('retains shared content in the submitted map after editing a translation', async () => {
		const { element, changes } = await richtext({ zxx: doc('Shared text'), en: null, de: null });
		element.locale = 'en';
		await tick();
		await editSource(element, '<p>Edited English</p>');

		expect(changes).toHaveBeenLastCalledWith({
			format: 'cosray-richtext',
			version: 1,
			value: { zxx: doc('Shared text'), en: doc('Edited English'), de: null },
		});
		element.locale = 'de';
		await tick();
		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain(
			'Edited English',
		);
		expect(element.querySelector('[contenteditable="true"]')?.textContent).toBe('');

		element.locale = 'en';
		await tick();
		await editSource(element, '<p></p>');
		element.locale = 'de';
		await tick();
		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain('Shared text');
		expect(changes).toHaveBeenLastCalledWith({
			format: 'cosray-richtext',
			version: 1,
			value: {
				zxx: doc('Shared text'),
				en: { type: 'doc', content: [{ type: 'paragraph', attrs: { class: 'default' } }] },
				de: null,
			},
		});
	});

	it('converts shared legacy HTML without filling missing translations', async () => {
		const { element, changes } = await richtext(
			{ zxx: '<p>Shared text</p>', en: '', de: '' },
			'html',
		);

		expect(element.querySelector('.cms-richtext-fallback')?.textContent).toContain('Shared text');
		expect(element.querySelector('[contenteditable="true"]')?.textContent).toBe('');
		expect(changes).toHaveBeenLastCalledWith({
			format: 'cosray-richtext',
			version: 1,
			value: { zxx: doc('Shared text'), en: null, de: null },
		});
	});
});
