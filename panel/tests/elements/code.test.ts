import { afterEach, describe, expect, it, vi } from 'vitest';

import type { HostPayload } from '../../src/lib/host';
import '../../src/elements/code.js';

vi.mock('../../src/lib/locale.js', () => ({ __: (id: string) => id }));

type CodeElement = HTMLElement & HostPayload & { locale: string };
type Change = { value: Record<string, string>; meta: { syntax: Record<string, string> } };

const locales = {
	default: 'en',
	all: [
		{ id: 'en', title: 'English' },
		{ id: 'de', title: 'Deutsch', fallback: 'en' },
	],
};

async function code(props: Partial<HostPayload> & { locale?: string }) {
	const element = document.createElement('cosray-code') as CodeElement;
	Object.assign(element, { field: { name: 'snippet' }, locales, ...props });
	const changes = vi.fn<(detail: Change) => void>();
	element.addEventListener('cosray-change', (event) => {
		changes(JSON.parse(JSON.stringify((event as CustomEvent).detail)));
	});
	document.body.append(element);
	await vi.waitFor(() => expect(input(element)).not.toBeNull());

	return { element, changes };
}

function input(element: HTMLElement): HTMLTextAreaElement {
	return element.querySelector<HTMLTextAreaElement>(
		'.cms-code-editor:not(.cms-code-editor-preview) textarea',
	)!;
}

function type(element: HTMLElement, text: string): void {
	const textarea = input(element);
	textarea.value += text;
	textarea.dispatchEvent(new InputEvent('input', { inputType: 'insertText', data: text }));
}

function undo(element: HTMLElement): void {
	input(element).dispatchEvent(
		new InputEvent('beforeinput', { inputType: 'historyUndo', cancelable: true }),
	);
}

afterEach(() => {
	document.body.replaceChildren();
});

describe('code editing', () => {
	it('edits the neutral value and reports it with the syntax', async () => {
		const { element, changes } = await code({
			value: { zxx: 'a = 1' },
			field: { name: 'snippet', syntaxes: ['php'] },
		});

		expect(input(element).value).toBe('a = 1');
		type(element, ';');

		expect(changes).toHaveBeenLastCalledWith({
			value: { zxx: 'a = 1;' },
			meta: { syntax: { zxx: 'php' } },
		});
		// The host submits the value; the editor must not add a form entry.
		expect(input(element).name).toBe('');
		expect(element.querySelector('select')).toBeNull();
	});

	it('undoes its own edits', async () => {
		const { element } = await code({ value: { zxx: 'a' } });

		type(element, 'b');
		undo(element);

		expect(input(element).value).toBe('a');
	});

	it('switches the edited language without reporting or undoing across languages', async () => {
		const { element, changes } = await code({
			value: { en: 'english', de: 'deutsch' },
			field: { name: 'snippet', translate: true },
			locale: 'en',
		});

		type(element, '!');
		changes.mockClear();
		element.locale = 'de';
		await vi.waitFor(() => expect(input(element).value).toBe('deutsch'));

		undo(element);
		expect(input(element).value).toBe('deutsch');
		expect(changes).not.toHaveBeenCalled();
		type(element, '!');
		expect(changes).toHaveBeenLastCalledWith({
			value: { en: 'english!', de: 'deutsch!' },
			meta: { syntax: { zxx: 'plaintext' } },
		});
	});

	it('shows the fallback behind an empty translation until the editor has focus', async () => {
		const { element } = await code({
			value: { en: 'english', de: '' },
			field: { name: 'snippet', translate: true },
			locale: 'de',
		});
		const fallback = () => element.querySelector('.cms-code-editor-fallback');

		await vi.waitFor(() => expect(fallback()?.textContent).toContain('english'));
		expect(fallback()?.querySelector('span')?.textContent).toBe('field:fallback-from');

		input(element).focus();
		expect(fallback()).toBeNull();
		input(element).blur();
		await vi.waitFor(() => expect(fallback()?.textContent).toContain('english'));

		type(element, 'x');
		expect(fallback()).toBeNull();
	});

	it('shows the fallback once the translation is cleared', async () => {
		const { element } = await code({
			value: { en: 'english', de: 'x' },
			field: { name: 'snippet', translate: true },
			locale: 'de',
		});
		const fallback = () => element.querySelector('.cms-code-editor-fallback');

		expect(fallback()).toBeNull();
		input(element).value = '';
		input(element).dispatchEvent(new InputEvent('input', { inputType: 'deleteContentBackward' }));

		await vi.waitFor(() => expect(fallback()?.textContent).toContain('english'));
	});

	it('lets the browser require a value when the field does', async () => {
		const { element } = await code({
			value: { zxx: '' },
			field: { name: 'snippet', required: true },
		});
		const form = document.createElement('form');
		document.body.append(form);
		form.append(element);
		await Promise.resolve();

		expect(input(element).required).toBe(true);
		expect(form.checkValidity()).toBe(false);
		type(element, 'x');
		expect(form.checkValidity()).toBe(true);
	});

	it('offers the field syntaxes and falls back to the first for an unknown choice', async () => {
		const { element, changes } = await code({
			value: { zxx: '' },
			meta: { syntax: { zxx: 'sql' } },
			field: { name: 'snippet', syntaxes: ['php', 'javascript'] },
		});
		const select = element.querySelector('select')!;

		expect(select.value).toBe('php');
		expect(element.querySelector('label')?.htmlFor).toBe(select.id);
		select.value = 'javascript';
		select.dispatchEvent(new Event('change'));

		expect(changes).toHaveBeenLastCalledWith({
			value: { zxx: '' },
			meta: { syntax: { zxx: 'javascript' } },
		});
	});

	it('names the syntaxes it offers and keeps unknown keys as configured', async () => {
		const { element } = await code({
			value: { zxx: '' },
			field: { name: 'snippet', syntaxes: ['plaintext', 'csharp', 'js', 'cobol'] },
		});
		const options = [...element.querySelector('select')!.options];

		expect(options.map((option) => [option.value, option.text])).toEqual([
			['plaintext', 'code:plaintext'],
			['csharp', 'C#'],
			['js', 'JavaScript'],
			['cobol', 'cobol'],
		]);
	});

	it('highlights the chosen syntax', async () => {
		const { element } = await code({
			value: { zxx: '<?php echo 1;' },
			field: { name: 'snippet', syntaxes: ['php'] },
		});

		await vi.waitFor(() =>
			expect(element.querySelector('.token.keyword')?.textContent).toBe('echo'),
		);
	});

	it('keeps an immutable field read-only', async () => {
		const { element } = await code({
			value: { zxx: 'locked' },
			field: { name: 'snippet', immutable: true, syntaxes: ['php', 'css'] },
		});
		const edit = new InputEvent('beforeinput', {
			inputType: 'insertText',
			data: 'x',
			cancelable: true,
		});

		input(element).dispatchEvent(edit);

		expect(edit.defaultPrevented).toBe(true);
		expect(element.querySelector('select')!.disabled).toBe(true);
	});

	it('keeps its edits when moved and tears the editor down when removed', async () => {
		const { element } = await code({ value: { zxx: 'kept' } });
		type(element, '!');
		const other = document.createElement('div');
		document.body.append(other);

		other.append(element);
		await Promise.resolve();
		expect(input(element).value).toBe('kept!');

		element.remove();
		await Promise.resolve();
		expect(element.querySelector('textarea')).toBeNull();

		document.body.append(element);
		await vi.waitFor(() => expect(input(element).value).toBe('kept!'));
	});
});
