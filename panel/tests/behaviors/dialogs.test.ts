import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { activate } from '@celema/verba';
import { ask, install } from '../../src/behaviors/dialogs';
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
		expect(dialog.querySelector('.modal-title')!.textContent).toBe('Delete');
		expect(dialog.querySelector('.question')!.textContent).toBe('Delete this entry?');
		const action = dialog.querySelector<HTMLButtonElement>('.modal-footer .danger')!;
		expect(action.textContent).toBe('Delete');
		// Destructive questions start on Cancel.
		expect(document.activeElement).toBe(dialog.querySelector('.modal-footer .secondary'));

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
		asking().querySelector<HTMLButtonElement>('.modal-footer .secondary')!.click();
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
		expect(asking().querySelector('.modal-title')!.textContent).toBe('Unsaved changes');
		asking().querySelector<HTMLButtonElement>('.modal-footer .danger')!.click();
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
