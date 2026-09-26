import { undo } from '@codemirror/commands';
import { EditorView } from '@codemirror/view';
import { afterEach, describe, expect, it, vi } from 'vitest';

import type { HostPayload } from '../../src/lib/host';
import '../../src/elements/code.js';

vi.mock('$lib/locale', () => ({ __: (id: string) => id }));

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
	await vi.waitFor(() => expect(view(element)).toBeDefined());

	return { element, changes };
}

function view(element: HTMLElement): EditorView {
	const dom = element.querySelector<HTMLElement>(
		'.cms-code-editor:not(.cms-code-editor-preview) .cm-editor',
	);

	return dom ? (EditorView.findFromDOM(dom) ?? undefined)! : undefined!;
}

function type(element: HTMLElement, text: string): void {
	const editor = view(element);
	editor.dispatch({ changes: { from: editor.state.doc.length, insert: text }, userEvent: 'input' });
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

		expect(view(element).state.doc.toString()).toBe('a = 1');
		type(element, ';');

		expect(changes).toHaveBeenLastCalledWith({
			value: { zxx: 'a = 1;' },
			meta: { syntax: { zxx: 'php' } },
		});
		const input = element.querySelector('textarea')!;
		expect(input.value).toBe('a = 1;');
		// The host submits the value; the mirror must not add a form entry.
		expect(input.name).toBe('');
		expect(element.querySelector('select')).toBeNull();
	});

	it('switches the edited language without reporting or undoing across languages', async () => {
		const { element, changes } = await code({
			value: { en: 'english', de: 'deutsch' },
			field: { name: 'snippet', translate: true },
			locale: 'en',
		});

		element.locale = 'de';
		await vi.waitFor(() => expect(view(element).state.doc.toString()).toBe('deutsch'));

		expect(undo(view(element))).toBe(false);
		expect(changes).not.toHaveBeenCalled();
		type(element, '!');
		expect(changes).toHaveBeenLastCalledWith({
			value: { en: 'english', de: 'deutsch!' },
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

		// jsdom cannot focus contenteditable, so the focus events come by hand.
		const content = view(element).contentDOM;
		content.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
		expect(fallback()).toBeNull();
		content.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
		await vi.waitFor(() => expect(fallback()?.textContent).toContain('english'));

		type(element, 'x');
		expect(fallback()).toBeNull();
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

	it('keeps an immutable field read-only', async () => {
		const { element } = await code({
			value: { zxx: 'locked' },
			field: { name: 'snippet', immutable: true, syntaxes: ['php', 'css'] },
		});

		expect(view(element).state.readOnly).toBe(true);
		expect(element.querySelector('select')!.disabled).toBe(true);
	});

	it('keeps its edits when moved and tears the editor down when removed', async () => {
		const { element } = await code({ value: { zxx: 'kept' } });
		type(element, '!');
		const other = document.createElement('div');
		document.body.append(other);

		other.append(element);
		await Promise.resolve();
		expect(view(element).state.doc.toString()).toBe('kept!');

		element.remove();
		await Promise.resolve();
		expect(element.querySelector('.cm-editor')).toBeNull();

		document.body.append(element);
		await vi.waitFor(() => expect(view(element).state.doc.toString()).toBe('kept!'));
	});
});
