import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/bulk';

let uninstall: (() => void) | null = null;

// jsdom versions without <dialog> support get a minimal stand-in so the
// open/close paths stay testable.
if (typeof HTMLDialogElement.prototype.showModal !== 'function') {
	HTMLDialogElement.prototype.showModal = function (this: HTMLDialogElement): void {
		this.setAttribute('open', '');
	};
	HTMLDialogElement.prototype.close = function (this: HTMLDialogElement): void {
		this.removeAttribute('open');
	};
}

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
						<input type="checkbox" name="nodes[]" value="a" data-bulk-check data-has-children />
					</td>
				</tr>
				<tr>
					<td><input type="checkbox" name="nodes[]" value="b" data-bulk-check /></td>
				</tr>
				<tr>
					<td><input type="checkbox" name="nodes[]" value="c" data-bulk-check /></td>
				</tr>
			</tbody>
		</table>
		<dialog data-bulk-dialog="delete">
			<p
				data-bulk-question
				data-label-one="Delete the selected entry?"
				data-label-many="Delete the :count selected entries?"></p>
			<label data-bulk-children hidden>
				<input type="checkbox" name="children" value="1" />
			</label>
			<button type="button" data-bulk-close>Cancel</button>
		</dialog>
	`;
	uninstall = install();
});

afterEach(() => {
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

		query('[data-bulk-close]').click();

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

		query('[data-bulk-close]').click();
		box('a').click();
		query('[data-bulk-open]').click();

		expect(query('[data-bulk-children]').hidden).toBe(false);
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
		query('[data-bulk-close]').click();
		query('[data-bulk-open]').click();

		expect(children.checked).toBe(false);
	});
});
