import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/chrome';
import { closeDialog } from '../../src/lib/dialogs';
import { install as installMenus } from '../../src/lib/action-menu';

let uninstall: () => void;

beforeEach(() => {
	uninstall = install();
});

afterEach(() => {
	for (const dialog of document.querySelectorAll('dialog')) closeDialog(dialog);
	uninstall();
	document.body.replaceChildren();
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
			<div data-meta-owner>
				<button type="button" data-meta-open><span>Metadata</span></button>
				<dialog data-meta><h2>Metadata</h2><input></dialog>
			</div>
			<div data-meta-owner><dialog data-meta></dialog></div>
		`;
		const [expected, other] = document.querySelectorAll('dialog');
		document.querySelector<HTMLElement>('[data-meta-open] span')!.click();
		expect(expected.open).toBe(true);
		expect(other.open).toBe(false);
		expect(document.activeElement).toBe(expected.querySelector('input'));
	});

	it('opens the dialog of the block row, not of the field around it', () => {
		document.body.innerHTML = `
			<div data-meta-owner>
				<button type="button" class="meta-button" data-meta-open>Field meta</button>
				<div data-meta-owner>
					<button type="button" class="gear" data-meta-open>Block meta</button>
					<div data-meta-owner><dialog data-meta id="sub"></dialog></div>
					<dialog data-meta id="block"></dialog>
				</div>
				<dialog data-meta id="field"></dialog>
			</div>
		`;
		const block = document.querySelector<HTMLDialogElement>('#block')!;
		const field = document.querySelector<HTMLDialogElement>('#field')!;
		document.querySelector<HTMLElement>('.gear')!.click();
		expect(block.open).toBe(true);
		expect(field.open).toBe(false);
		closeDialog(block);
		document.querySelector<HTMLElement>('.meta-button')!.click();
		expect(field.open).toBe(true);
		expect(document.querySelector<HTMLDialogElement>('#sub')!.open).toBe(false);
	});

	it('retains live settings and form ownership without submitting on close', () => {
		document.body.innerHTML = `
			<form><div data-meta-owner>
				<button type="button" data-meta-open>Metadata</button>
				<dialog data-meta><h2>Settings</h2>
					<input name="content[body][meta][class][zxx]" value="original">
					<button type="button" data-dialog-close><span>Close</span></button>
				</dialog>
			</div></form>
		`;
		const form = document.querySelector('form')!;
		const submit = vi.fn((event: Event) => event.preventDefault());
		form.addEventListener('submit', submit);
		const opener = document.querySelector<HTMLButtonElement>('[data-meta-open]')!;
		opener.click();
		const input = form.querySelector('input')!;
		expect(input.form).toBe(form);
		input.value = 'edited';
		input.dispatchEvent(new Event('input', { bubbles: true }));
		document.querySelector<HTMLElement>('[data-dialog-close] span')!.click();
		expect(form.querySelector('dialog')!.open).toBe(false);
		expect(new FormData(form).get(input.name)).toBe('edited');
		expect(submit).not.toHaveBeenCalled();
		expect(document.activeElement).toBe(opener);
		opener.click();
		expect(input.value).toBe('edited');
	});

	it('returns from a menu-opened settings dialog to the visible menu trigger', async () => {
		document.body.innerHTML = `<div data-meta-owner>
			<button type="button" popovertarget="settings-actions">Actions</button>
			<div id="settings-actions" popover="auto" data-action-menu>
				<button type="button" data-meta-open>Settings</button>
			</div>
			<dialog data-meta><h2>Settings</h2><input></dialog>
		</div>`;
		const stopMenus = installMenus();
		try {
			const trigger = document.querySelector<HTMLButtonElement>('[popovertarget]')!;
			vi.spyOn(trigger, 'getBoundingClientRect').mockReturnValue(new DOMRect(100, 100, 32, 24));
			trigger.click();
			await Promise.resolve();
			document.querySelector<HTMLButtonElement>('[data-meta-open]')!.click();
			const dialog = document.querySelector('dialog')!;
			expect(document.activeElement).toBe(dialog.querySelector('input'));
			closeDialog(dialog);
			expect(document.activeElement).toBe(trigger);
		} finally {
			stopMenus();
		}
	});

	it('ignores metadata controls outside their required containers', () => {
		document.body.innerHTML =
			'<button type="button" data-meta-open>Open</button><dialog data-meta></dialog>';
		document.querySelector<HTMLElement>('[data-meta-open]')!.click();
		expect(document.querySelector('dialog')!.open).toBe(false);
	});

	it('removes its delegated click listener on uninstall', () => {
		document.body.innerHTML =
			'<div id="editor-preview" class="is-open"><button type="button" data-overlay-close>Close</button></div>';
		const overlay = document.querySelector<HTMLElement>('#editor-preview')!;
		uninstall();
		document.querySelector<HTMLElement>('[data-overlay-close]')!.click();
		expect(overlay.hidden).toBe(false);
		expect(overlay.className).toBe('is-open');
	});
});
