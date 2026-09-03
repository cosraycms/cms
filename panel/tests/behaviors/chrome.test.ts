import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { install } from '../../src/behaviors/chrome';

const showModalDescriptor = Object.getOwnPropertyDescriptor(
	HTMLDialogElement.prototype,
	'showModal',
);
const closeDescriptor = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');

let showModal = vi.fn();
let close = vi.fn();
let uninstall = (): void => undefined;

beforeEach(() => {
	showModal = vi.fn();
	close = vi.fn();
	Object.defineProperty(HTMLDialogElement.prototype, 'showModal', {
		configurable: true,
		value: showModal,
	});
	Object.defineProperty(HTMLDialogElement.prototype, 'close', {
		configurable: true,
		value: close,
	});
	uninstall = install();
});

afterEach(() => {
	uninstall();
	document.body.replaceChildren();

	if (showModalDescriptor) {
		Object.defineProperty(HTMLDialogElement.prototype, 'showModal', showModalDescriptor);
	} else {
		delete (HTMLDialogElement.prototype as { showModal?: () => void }).showModal;
	}

	if (closeDescriptor) {
		Object.defineProperty(HTMLDialogElement.prototype, 'close', closeDescriptor);
	} else {
		delete (HTMLDialogElement.prototype as { close?: () => void }).close;
	}
});

describe('editor chrome', () => {
	it('empties and hides the preview overlay without removing its anchor', () => {
		document.body.innerHTML = `
			<div id="editor-preview" class="cms-preview is-open">
				<button type="button" data-overlay-close><span>Close</span></button>
				<iframe></iframe>
			</div>
		`;
		const overlay = document.querySelector<HTMLElement>('#editor-preview')!;

		document.querySelector<HTMLElement>('[data-overlay-close] span')!.click();

		expect(overlay.isConnected).toBe(true);
		expect(overlay.hidden).toBe(true);
		expect(overlay.childElementCount).toBe(0);
		expect(overlay.hasAttribute('class')).toBe(false);
	});

	it('opens the metadata dialog belonging to the clicked field', () => {
		document.body.innerHTML = `
			<div class="cms-field" data-meta-owner>
				<button type="button" data-meta-open><span>Metadata</span></button>
				<dialog data-meta></dialog>
			</div>
			<div class="cms-field" data-meta-owner><dialog data-meta></dialog></div>
		`;
		const expected = document.querySelector<HTMLDialogElement>('dialog[data-meta]')!;

		document.querySelector<HTMLElement>('[data-meta-open] span')!.click();

		expect(showModal).toHaveBeenCalledOnce();
		expect(showModal.mock.instances[0]).toBe(expected);
	});

	it('opens the dialog of the block row, not of the field around it', () => {
		document.body.innerHTML = `
			<div class="cms-field" data-meta-owner>
				<button type="button" class="meta-button" data-meta-open>Field meta</button>
				<div class="control">
					<div class="block" data-meta-owner>
						<button type="button" class="gear" data-meta-open>Block meta</button>
						<div class="body">
							<div class="cms-field" data-meta-owner>
								<dialog data-meta id="sub"></dialog>
							</div>
						</div>
						<dialog data-meta id="block"></dialog>
					</div>
				</div>
				<dialog data-meta id="field"></dialog>
			</div>
		`;

		document.querySelector<HTMLElement>('.gear')!.click();
		document.querySelector<HTMLElement>('.meta-button')!.click();

		expect(showModal).toHaveBeenCalledTimes(2);
		expect((showModal.mock.instances[0] as HTMLElement).id).toBe('block');
		expect((showModal.mock.instances[1] as HTMLElement).id).toBe('field');
	});

	it('closes the metadata dialog containing the clicked control', () => {
		document.body.innerHTML = `
			<dialog data-meta>
				<button type="button" data-meta-close><span>Close</span></button>
			</dialog>
		`;
		const expected = document.querySelector<HTMLDialogElement>('dialog[data-meta]')!;

		document.querySelector<HTMLElement>('[data-meta-close] span')!.click();

		expect(close).toHaveBeenCalledOnce();
		expect(close.mock.instances[0]).toBe(expected);
	});

	it('ignores metadata controls outside their required containers', () => {
		document.body.innerHTML = `
			<button type="button" data-meta-open>Open</button>
			<button type="button" data-meta-close>Close</button>
		`;

		document.querySelector<HTMLElement>('[data-meta-open]')!.click();
		document.querySelector<HTMLElement>('[data-meta-close]')!.click();

		expect(showModal).not.toHaveBeenCalled();
		expect(close).not.toHaveBeenCalled();
	});

	it('removes its delegated click listener on uninstall', () => {
		document.body.innerHTML = `
			<div id="editor-preview" class="is-open">
				<button type="button" data-overlay-close>Close</button>
			</div>
		`;
		const overlay = document.querySelector<HTMLElement>('#editor-preview')!;
		uninstall();
		uninstall = (): void => undefined;

		document.querySelector<HTMLElement>('[data-overlay-close]')!.click();

		expect(overlay.hidden).toBe(false);
		expect(overlay.className).toBe('is-open');
	});
});
