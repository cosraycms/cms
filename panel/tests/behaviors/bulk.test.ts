import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/bulk';
import { closeDialog } from '../../src/lib/dialogs';

let uninstall: (() => void) | null = null;

beforeEach(() => {
	document.body.innerHTML = `
		<form id="collection-bulk" method="post" hidden></form>
		<div data-bulk-bar hidden>
			<output
				data-bulk-count
				data-label-one=":count entry selected"
				data-label-many=":count entries selected"></output>
			<button type="button" data-bulk-clear>Clear</button>
			<button type="button" data-bulk-open="delete">Delete</button>
			<button type="button" data-bulk-open="duplicate">Duplicate</button>
		</div>
		<table>
			<thead>
				<tr>
					<th><input type="checkbox" data-bulk-all /></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<input type="checkbox" name="nodes[]" form="collection-bulk" value="a" data-bulk-check data-has-children />
					</td>
				</tr>
				<tr>
					<td><input type="checkbox" name="nodes[]" form="collection-bulk" value="b" data-bulk-check /></td>
				</tr>
				<tr>
					<td><input type="checkbox" name="nodes[]" form="collection-bulk" value="c" data-bulk-check /></td>
				</tr>
			</tbody>
		</table>
		<dialog data-bulk-dialog="delete">
			<p
				data-bulk-question
				data-label-one="Delete the selected entry?"
				data-label-many="Delete the :count selected entries?"></p>
			<label data-bulk-children data-bulk-gate hidden>
				<input type="checkbox" name="children" form="collection-bulk" value="1" />
			</label>
			<button type="button" data-dialog-close data-dialog-focus>Cancel</button>
			<button type="submit" form="collection-bulk" formaction="/bulk/delete" data-bulk-confirm>Delete</button>
		</dialog>
		<dialog data-bulk-dialog="duplicate">
			<p
				data-bulk-question
				data-label-one="Duplicate the selected entry?"
				data-label-many="Duplicate the :count selected entries?"></p>
			<label data-bulk-children hidden>
				<input type="checkbox" name="children" form="collection-bulk" value="1" />
			</label>
			<button type="button" data-dialog-close data-dialog-focus>Cancel</button>
			<button type="submit" form="collection-bulk" formaction="/bulk/duplicate" data-bulk-confirm>Duplicate</button>
		</dialog>
	`;
	uninstall = install();
});

afterEach(() => {
	for (const dialog of document.querySelectorAll('dialog')) closeDialog(dialog);
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
});

function query<T extends HTMLElement>(selector: string): T {
	const el = document.querySelector<T>(selector);

	if (!el) {
		throw new Error(`missing ${selector}`);
	}

	return el;
}

function box(value: string): HTMLInputElement {
	return query<HTMLInputElement>(`input[value="${value}"]`);
}

function bar(): HTMLElement {
	return query('[data-bulk-bar]');
}

describe('bulk selection', () => {
	it('reveals the bar and counts the selection', () => {
		expect(bar().hidden).toBe(true);

		box('a').click();

		expect(bar().hidden).toBe(false);
		expect(query('[data-bulk-count]').textContent).toBe('1 entry selected');

		box('b').click();

		expect(query('[data-bulk-count]').textContent).toBe('2 entries selected');
	});

	it('hides the bar again when the selection empties', () => {
		box('a').click();
		box('a').click();

		expect(bar().hidden).toBe(true);
	});

	it('selects and deselects everything through the header checkbox', () => {
		const master = query<HTMLInputElement>('[data-bulk-all]');

		master.click();

		expect(box('a').checked).toBe(true);
		expect(box('b').checked).toBe(true);
		expect(box('c').checked).toBe(true);
		expect(query('[data-bulk-count]').textContent).toBe('3 entries selected');

		master.click();

		expect(box('a').checked).toBe(false);
		expect(bar().hidden).toBe(true);
	});

	it('mirrors the selection state onto the header checkbox', () => {
		const master = query<HTMLInputElement>('[data-bulk-all]');

		box('a').click();

		expect(master.checked).toBe(false);
		expect(master.indeterminate).toBe(true);

		box('b').click();
		box('c').click();

		expect(master.checked).toBe(true);
		expect(master.indeterminate).toBe(false);
	});

	it('clears the selection', () => {
		box('a').click();
		box('b').click();
		query('[data-bulk-clear]').click();

		expect(box('a').checked).toBe(false);
		expect(box('b').checked).toBe(false);
		expect(bar().hidden).toBe(true);
	});

	it('opens the dialog with the count and closes it again', () => {
		box('b').click();
		box('c').click();
		query('[data-bulk-open]').click();

		const dialog = query<HTMLDialogElement>('dialog[data-bulk-dialog="delete"]');

		expect(dialog.open).toBe(true);
		expect(query('[data-bulk-question]').textContent).toBe('Delete the 2 selected entries?');

		query('[data-dialog-close]').click();

		expect(dialog.open).toBe(false);
	});

	it('does not open the dialog without a selection', () => {
		query('[data-bulk-open]').click();

		expect(query<HTMLDialogElement>('dialog[data-bulk-dialog="delete"]').open).toBe(false);
	});

	it('offers the children option only when a selected row has children', () => {
		box('b').click();
		query('[data-bulk-open]').click();

		expect(query('[data-bulk-children]').hidden).toBe(true);

		query('[data-dialog-close]').click();
		box('a').click();
		query('[data-bulk-open]').click();

		expect(query('[data-bulk-children]').hidden).toBe(false);
	});

	it('keeps the confirm button enabled for a selection without children', () => {
		box('b').click();
		query('[data-bulk-open]').click();

		expect(query<HTMLButtonElement>('[data-bulk-confirm]').disabled).toBe(false);
	});

	it('locks the confirm button behind the children opt-in', () => {
		box('a').click();
		query('[data-bulk-open]').click();

		const confirm = query<HTMLButtonElement>('[data-bulk-confirm]');
		const children = query<HTMLInputElement>('[data-bulk-children] input');

		expect(confirm.disabled).toBe(true);

		children.click();

		expect(confirm.disabled).toBe(false);

		children.click();

		expect(confirm.disabled).toBe(true);
	});

	it('never locks the confirm button of an ungated dialog', () => {
		box('a').click();
		query('[data-bulk-open="duplicate"]').click();

		const dialog = query<HTMLDialogElement>('dialog[data-bulk-dialog="duplicate"]');
		const children = dialog.querySelector<HTMLElement>('[data-bulk-children]');
		const confirm = dialog.querySelector<HTMLButtonElement>('[data-bulk-confirm]');
		const checkbox = dialog.querySelector<HTMLInputElement>('[data-bulk-children] input');

		expect(children?.hidden).toBe(false);
		expect(confirm?.disabled).toBe(false);

		checkbox?.click();
		checkbox?.click();

		expect(confirm?.disabled).toBe(false);
	});

	it('locks the confirm button again when the dialog reopens', () => {
		box('a').click();
		query('[data-bulk-open]').click();
		query<HTMLInputElement>('[data-bulk-children] input').click();
		query('[data-dialog-close]').click();
		query('[data-bulk-open]').click();

		expect(query<HTMLButtonElement>('[data-bulk-confirm]').disabled).toBe(true);
	});

	it.each(['button', 'escape'])('cancels with %s without submitting the selection', (path) => {
		const submit = vi.fn((event: Event) => event.preventDefault());
		query<HTMLFormElement>('#collection-bulk').addEventListener('submit', submit);
		box('b').click();
		const opener = query<HTMLButtonElement>('[data-bulk-open="delete"]');
		opener.click();
		const dialog = query<HTMLDialogElement>('dialog[data-bulk-dialog="delete"]');
		expect(document.activeElement).toBe(dialog.querySelector('[data-dialog-focus]'));
		if (path === 'button') query('[data-dialog-close]').click();
		else dialog.dispatchEvent(new Event('cancel', { cancelable: true }));
		expect(submit).not.toHaveBeenCalled();
		expect(dialog.open).toBe(false);
		expect(document.activeElement).toBe(opener);
		expect(box('b').checked).toBe(true);
	});

	it('submits the chosen action and subtree opt-in once after closing', () => {
		const form = query<HTMLFormElement>('#collection-bulk');
		const submit = vi.fn((event: SubmitEvent) => {
			event.preventDefault();
			expect(event.submitter?.getAttribute('formaction')).toBe('/bulk/delete');
			expect(new FormData(form).getAll('nodes[]')).toEqual(['a']);
			expect(new FormData(form).get('children')).toBe('1');
			expect(query<HTMLDialogElement>('dialog[data-bulk-dialog="delete"]').open).toBe(false);
		});
		form.addEventListener('submit', submit);
		box('a').click();
		query('[data-bulk-open="delete"]').click();
		query<HTMLInputElement>('[data-bulk-children] input').click();
		query('[data-bulk-confirm]').click();
		expect(submit).toHaveBeenCalledOnce();
	});

	it('drops the notice param at install time', () => {
		uninstall?.();
		history.replaceState(null, '', '/cp/collection/x?q=foo&notice=deleted:1');
		uninstall = install();

		expect(window.location.search).toBe('?q=foo');
	});

	it('drops the notice param after a swap settles', async () => {
		history.replaceState(null, '', '/cp/collection/x?notice=deleted:1');
		document.dispatchEvent(new Event('htmx:after:swap'));
		await new Promise((resolve) => setTimeout(resolve, 1));

		expect(window.location.search).toBe('');
	});

	it('resets the children checkbox every time the dialog opens', () => {
		box('a').click();
		query('[data-bulk-open]').click();

		const children = query<HTMLInputElement>('[data-bulk-children] input');

		children.checked = true;
		query('[data-dialog-close]').click();
		query('[data-bulk-open]').click();

		expect(children.checked).toBe(false);
	});
});
