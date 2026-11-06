import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { activate } from '@celema/verba';
import { install } from '../../src/behaviors/dirty';
import { catalog } from '../catalog';

beforeAll(() => activate(catalog()));

let uninstall: (() => void) | null = null;

beforeEach(() => {
	document.body.innerHTML = `
		<span id="editor-dirty" hidden></span>
		<span id="editor-status"></span>
		<form id="node-editor-form">
			<select data-editor-state><option>en</option><option>de</option></select>
			<input name="content[title][value][zxx]">
		</form>
		<a id="outside" href="/elsewhere">Away</a>
	`;
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	// The next beforeEach detaches this form, which resets the
	// form-element-anchored dirty state by construction.
	document.body.innerHTML = '';
	vi.restoreAllMocks();
});

function edit(): void {
	document
		.querySelector('#node-editor-form input')
		?.dispatchEvent(new Event('input', { bubbles: true }));
}

type Context = { confirm?: string | null; sourceElement?: Element };

/** The question a request configured from the source would ask, if any. */
function request(source: Element | null, confirm: string | null = null): string | null {
	const ctx: Context = { confirm, sourceElement: source ?? undefined };
	(source ?? document).dispatchEvent(
		new CustomEvent('htmx:config:request', { bubbles: true, cancelable: true, detail: { ctx } }),
	);

	return ctx.confirm ?? null;
}

function indicator(): HTMLElement | null {
	return document.getElementById('editor-dirty');
}

describe('dirty guard', () => {
	it('shows the indicator on an edit inside the form', () => {
		expect(indicator()?.hidden).toBe(true);

		edit();

		expect(indicator()?.hidden).toBe(false);
	});

	it('does not mark editor-only state changes', () => {
		const select = document.querySelector<HTMLSelectElement>('[data-editor-state]')!;
		select.value = 'de';
		select.dispatchEvent(new Event('change', { bubbles: true }));

		expect(indicator()?.hidden).toBe(true);
	});

	it('asks before a request leaves the editor with unsaved edits', () => {
		edit();

		expect(request(document.getElementById('outside'))).toBe(
			'There are unsaved changes. Leave this editor anyway?',
		);
	});

	it('says that the edits are kept where the editor autosaves them', () => {
		document.body.insertAdjacentHTML('beforeend', '<div id="editor-autosave" hidden></div>');
		edit();

		expect(request(document.getElementById('outside'))).toBe(
			'There are unsaved changes. They are kept and offered back when this entry is opened again. Leave the editor?',
		);
	});

	it('never questions requests originating inside the form', () => {
		edit();

		expect(request(document.querySelector('#node-editor-form input'))).toBeNull();
	});

	it('does not guard while pristine', () => {
		expect(request(document.getElementById('outside'))).toBeNull();
	});

	it('stands down after a successful save', () => {
		const status = document.getElementById('editor-status');

		edit();
		status?.setAttribute('data-saved', 'true');
		document.dispatchEvent(new Event('htmx:after:swap', { bubbles: true }));

		expect(indicator()?.hidden).toBe(true);
		expect(status?.hasAttribute('data-saved')).toBe(false);
		expect(request(document.getElementById('outside'))).toBeNull();
	});

	it('forgets dirty state anchored to a swapped-out form', () => {
		edit();
		document.getElementById('node-editor-form')?.remove();

		expect(request(document.getElementById('outside'))).toBeNull();
	});

	it('lets a request marked data-dirty-bypass through without asking', () => {
		const discard = document.createElement('form');
		discard.id = 'node-editor-discard';
		discard.setAttribute('data-dirty-bypass', '');
		document.body.append(discard);

		edit();

		expect(request(discard)).toBeNull();
	});

	it('keeps the question a request asks of its own', () => {
		edit();

		expect(request(document.getElementById('outside'), 'Delete this?')).toBe('Delete this?');
	});

	it('prevents unload while dirty', () => {
		edit();
		const event = new Event('beforeunload', { cancelable: true });
		window.dispatchEvent(event);

		expect(event.defaultPrevented).toBe(true);
	});
});
