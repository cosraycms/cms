import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/preview';

let uninstall: () => void;

beforeEach(() => {
	uninstall = install();
});

afterEach(() => {
	uninstall();
	document.body.replaceChildren();
});

function swapIn(): HTMLDialogElement {
	document.body.innerHTML = `
		<button type="submit" id="opener">Preview</button>
		<dialog id="editor-preview" class="cms-modal">
			<header><h2 data-dialog-title>Preview</h2><button type="button" data-dialog-close>Close</button></header>
			<iframe src="about:blank" title="Preview"></iframe>
		</dialog>
	`;
	document.querySelector<HTMLElement>('#opener')!.focus();
	document.dispatchEvent(new CustomEvent('htmx:after:swap'));

	return document.querySelector<HTMLDialogElement>('#editor-preview')!;
}

describe('editor preview', () => {
	it('opens the swapped-in preview as a modal dialog', () => {
		const dialog = swapIn();

		expect(dialog.open).toBe(true);
		expect(dialog.getAttribute('aria-labelledby')).toBeTruthy();
	});

	it('leaves an empty anchor closed', () => {
		document.body.innerHTML = '<dialog id="editor-preview"></dialog>';
		document.dispatchEvent(new CustomEvent('htmx:after:swap'));

		expect(document.querySelector<HTMLDialogElement>('#editor-preview')!.open).toBe(false);
	});

	it('empties the dialog on close, keeps its anchor and returns focus', () => {
		const dialog = swapIn();
		dialog.querySelector<HTMLElement>('[data-dialog-close]')!.click();

		expect(dialog.open).toBe(false);
		expect(dialog.isConnected).toBe(true);
		expect(dialog.childElementCount).toBe(0);
		expect(document.activeElement?.id).toBe('opener');
	});

	it('stops opening previews on uninstall', () => {
		uninstall();
		const dialog = swapIn();

		expect(dialog.open).toBe(false);
	});
});
