import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { activate } from '@celema/verba';
import { ask, install } from '../../src/behaviors/dialogs';
import { install as installMenus } from '../../src/lib/action-menu';
import { closeDialog } from '../../src/lib/dialogs';
import { catalog } from '../catalog';

let uninstall: () => void;

beforeAll(() => activate(catalog()));

beforeEach(() => {
	uninstall = install();
});

afterEach(() => {
	for (const dialog of document.querySelectorAll('dialog')) closeDialog(dialog);
	uninstall();
	document.body.replaceChildren();
});

type Detail = {
	ctx: { confirm?: string | null; sourceElement?: Element };
	issueRequest: () => void;
	dropRequest: () => void;
};

function request(source: Element, confirm: string | null): Detail & { event: CustomEvent } {
	const detail: Detail = {
		ctx: { confirm, sourceElement: source },
		issueRequest: vi.fn(),
		dropRequest: vi.fn(),
	};
	const event = new CustomEvent('htmx:confirm', { bubbles: true, cancelable: true, detail });
	source.dispatchEvent(event);

	return { ...detail, event };
}

function asking(): HTMLDialogElement {
	return document.querySelector<HTMLDialogElement>('dialog.cms-modal[open]')!;
}

function settle(): Promise<void> {
	return new Promise((resolve) => setTimeout(resolve));
}

describe('dialog triggers', () => {
	it('opens the metadata dialog belonging to the clicked field', () => {
		document.body.innerHTML = `
			<div data-meta-owner>
				<button type="button" data-dialog-open="meta"><span>Metadata</span></button>
				<dialog data-dialog="meta"><h2>Metadata</h2><input></dialog>
			</div>
			<div data-meta-owner><dialog data-dialog="meta"></dialog></div>
		`;
		const [expected, other] = document.querySelectorAll('dialog');
		document.querySelector<HTMLElement>('[data-dialog-open="meta"] span')!.click();
		expect(expected.open).toBe(true);
		expect(other.open).toBe(false);
		expect(document.activeElement).toBe(expected.querySelector('input'));
	});

	it('opens the dialog of the block row, not of the field around it', () => {
		document.body.innerHTML = `
			<div data-meta-owner>
				<button type="button" class="meta-button" data-dialog-open="meta">Field meta</button>
				<div data-meta-owner>
					<button type="button" class="gear" data-dialog-open="meta">Block meta</button>
					<div data-meta-owner><dialog data-dialog="meta" id="sub"></dialog></div>
					<dialog data-dialog="meta" id="block"></dialog>
				</div>
				<dialog data-dialog="meta" id="field"></dialog>
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
				<button type="button" data-dialog-open="meta">Metadata</button>
				<dialog data-dialog="meta"><h2>Settings</h2>
					<input name="content[body][meta][class][zxx]" value="original">
					<button type="button" data-dialog-close><span>Close</span></button>
				</dialog>
			</div></form>
		`;
		const form = document.querySelector('form')!;
		const submit = vi.fn((event: Event) => event.preventDefault());
		form.addEventListener('submit', submit);
		const opener = document.querySelector<HTMLButtonElement>('[data-dialog-open="meta"]')!;
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
				<button type="button" data-dialog-open="meta">Settings</button>
			</div>
			<dialog data-dialog="meta"><h2>Settings</h2><input></dialog>
		</div>`;
		const stopMenus = installMenus();
		try {
			const trigger = document.querySelector<HTMLButtonElement>('[popovertarget]')!;
			vi.spyOn(trigger, 'getBoundingClientRect').mockReturnValue(new DOMRect(100, 100, 32, 24));
			trigger.click();
			await Promise.resolve();
			document.querySelector<HTMLButtonElement>('[data-dialog-open="meta"]')!.click();
			const dialog = document.querySelector('dialog')!;
			expect(document.activeElement).toBe(dialog.querySelector('input'));
			closeDialog(dialog);
			expect(document.activeElement).toBe(trigger);
		} finally {
			stopMenus();
		}
	});

	it('opens the nearest dialog of its name, not one further out or of another name', () => {
		document.body.innerHTML = `
			<section>
				<div><button type="button" data-dialog-open="paths">Paths</button></div>
				<dialog data-dialog="meta" id="other"></dialog>
				<dialog data-dialog="paths" id="near"></dialog>
			</section>
			<dialog data-dialog="paths" id="far"></dialog>
		`;
		document.querySelector<HTMLElement>('[data-dialog-open]')!.click();
		expect(document.querySelector<HTMLDialogElement>('#near')!.open).toBe(true);
		expect(document.querySelector<HTMLDialogElement>('#far')!.open).toBe(false);
		expect(document.querySelector<HTMLDialogElement>('#other')!.open).toBe(false);
	});

	it('ignores a trigger no ancestor holds a dialog of that name for', () => {
		document.body.innerHTML =
			'<button type="button" data-dialog-open="meta">Open</button><div><dialog data-dialog="meta"></dialog></div>';
		document.querySelector<HTMLElement>('[data-dialog-open]')!.click();
		expect(document.querySelector('dialog')!.open).toBe(false);
	});
});

describe('hx-confirm', () => {
	it('asks the question of the source in a panel dialog and issues the request on yes', async () => {
		document.body.innerHTML = `
			<form hx-confirm="Delete this entry?" data-confirm-action="Delete" data-confirm-tone="danger">
				<button type="submit">Delete</button>
			</form>
		`;
		const form = document.querySelector('form')!;
		const { event, issueRequest, dropRequest } = request(form, 'Delete this entry?');

		expect(event.defaultPrevented).toBe(true);
		const dialog = asking();
		expect(dialog.querySelector('.cms-modal-title')!.textContent).toBe('Delete');
		expect(dialog.querySelector('.question')!.textContent).toBe('Delete this entry?');
		const action = dialog.querySelector<HTMLButtonElement>('.cms-modal-footer .cms-button-danger')!;
		expect(action.textContent).toBe('Delete');
		// Destructive questions start on Cancel.
		expect(document.activeElement).toBe(
			dialog.querySelector('.cms-modal-footer .cms-button-secondary'),
		);

		action.click();
		await settle();

		expect(issueRequest).toHaveBeenCalledOnce();
		expect(dropRequest).not.toHaveBeenCalled();
		expect(document.querySelector('dialog')).toBeNull();
	});

	it('drops the request when the question is cancelled or dismissed', async () => {
		document.body.innerHTML = '<form hx-confirm="Revoke?"></form>';
		const form = document.querySelector('form')!;
		const cancelled = request(form, 'Revoke?');
		asking().querySelector<HTMLButtonElement>('.cms-modal-footer .cms-button-secondary')!.click();
		await settle();

		const dismissed = request(form, 'Revoke?');
		asking().dispatchEvent(new Event('cancel', { cancelable: true }));
		await settle();

		for (const asked of [cancelled, dismissed]) {
			expect(asked.dropRequest).toHaveBeenCalledOnce();
			expect(asked.issueRequest).not.toHaveBeenCalled();
		}
	});

	it('drops the request when its source leaves the document meanwhile', async () => {
		document.body.innerHTML = '<div><form hx-confirm="Delete?"></form></div>';
		const form = document.querySelector('form')!;
		const { dropRequest } = request(form, 'Delete?');
		form.remove();
		await settle();

		expect(dropRequest).toHaveBeenCalledOnce();
		expect(document.querySelector('dialog')).toBeNull();
	});

	it('asks a question a behavior put on the request instead of the attributes', async () => {
		document.body.innerHTML = '<a href="/elsewhere">Away</a>';
		const link = document.querySelector('a')!;
		const ctx: Detail['ctx'] = { sourceElement: link };
		ask(ctx, { title: 'Unsaved changes', message: 'Leave?', action: 'Leave', tone: 'danger' });
		const detail = { ctx, issueRequest: vi.fn(), dropRequest: vi.fn() };
		link.dispatchEvent(
			new CustomEvent('htmx:confirm', { bubbles: true, cancelable: true, detail }),
		);

		expect(ctx.confirm).toBe('Leave?');
		expect(asking().querySelector('.cms-modal-title')!.textContent).toBe('Unsaved changes');
		asking().querySelector<HTMLButtonElement>('.cms-modal-footer .cms-button-danger')!.click();
		await settle();

		expect(detail.issueRequest).toHaveBeenCalledOnce();
	});

	it('leaves requests without a question and js: confirmations to htmx', () => {
		document.body.innerHTML = '<form></form>';
		const form = document.querySelector('form')!;

		expect(request(form, null).event.defaultPrevented).toBe(false);
		expect(request(form, 'js:confirmed()').event.defaultPrevented).toBe(false);
		expect(document.querySelector('dialog')).toBeNull();
	});
});
