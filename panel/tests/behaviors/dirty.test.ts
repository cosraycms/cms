import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/dirty';

let uninstall: (() => void) | null = null;

beforeEach(() => {
	document.body.innerHTML = `
		<span id="editor-dirty" hidden></span>
		<span id="editor-status"></span>
		<form id="node-editor-form"><input name="content[title][value][zxx]"></form>
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

function request(source: Element | null): CustomEvent {
	const event = new CustomEvent('htmx:before:request', {
		bubbles: true,
		cancelable: true,
		detail: { ctx: { sourceElement: source ?? undefined } },
	});
	(source ?? document).dispatchEvent(event);

	return event;
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

	it('blocks navigation away when the user declines', () => {
		const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);

		edit();
		const event = request(document.getElementById('outside'));

		expect(confirm).toHaveBeenCalledOnce();
		expect(event.defaultPrevented).toBe(true);
	});

	it('allows navigation away when the user confirms', () => {
		vi.spyOn(window, 'confirm').mockReturnValue(true);

		edit();
		const event = request(document.getElementById('outside'));

		expect(event.defaultPrevented).toBe(false);
	});

	it('never questions requests originating inside the form', () => {
		const confirm = vi.spyOn(window, 'confirm');

		edit();
		const event = request(document.querySelector('#node-editor-form input'));

		expect(confirm).not.toHaveBeenCalled();
		expect(event.defaultPrevented).toBe(false);
	});

	it('does not guard while pristine', () => {
		const confirm = vi.spyOn(window, 'confirm');
		const event = request(document.getElementById('outside'));

		expect(confirm).not.toHaveBeenCalled();
		expect(event.defaultPrevented).toBe(false);
	});

	it('stands down after a successful save', () => {
		const confirm = vi.spyOn(window, 'confirm');
		const status = document.getElementById('editor-status');

		edit();
		status?.setAttribute('data-saved', 'true');
		document.dispatchEvent(new Event('htmx:after:swap', { bubbles: true }));

		expect(indicator()?.hidden).toBe(true);
		expect(status?.hasAttribute('data-saved')).toBe(false);

		const event = request(document.getElementById('outside'));

		expect(confirm).not.toHaveBeenCalled();
		expect(event.defaultPrevented).toBe(false);
	});

	it('forgets dirty state anchored to a swapped-out form', () => {
		const confirm = vi.spyOn(window, 'confirm');

		edit();
		document.getElementById('node-editor-form')?.remove();
		const event = request(document.getElementById('outside'));

		expect(confirm).not.toHaveBeenCalled();
		expect(event.defaultPrevented).toBe(false);
	});

	it('prevents unload while dirty', () => {
		edit();
		const event = new Event('beforeunload', { cancelable: true });
		window.dispatchEvent(event);

		expect(event.defaultPrevented).toBe(true);
	});
});
