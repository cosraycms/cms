import { afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import { activate } from '@celema/verba';
import { install } from '../../src/behaviors/create';
import { catalog } from '../catalog';

beforeAll(() => activate(catalog()));

const ajax = vi.fn();
let uninstall: () => void;

beforeEach(() => {
	HTMLDialogElement.prototype.showModal ??= function (this: HTMLDialogElement) {
		this.open = true;
	};
	HTMLDialogElement.prototype.close ??= function (this: HTMLDialogElement) {
		this.open = false;
	};
	vi.stubGlobal('htmx', { ajax });
	document.body.innerHTML =
		'<a id="new" href="/panel/node/create/article?parent=p" data-create-dialog>New entry</a>';
	uninstall = install();
});

afterEach(() => {
	uninstall();
	ajax.mockReset();
	vi.unstubAllGlobals();
	document.body.innerHTML = '';
});

it('loads the dialog of the entry type into a modal instead of following the link', () => {
	const click = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
	document.getElementById('new')!.dispatchEvent(click);

	expect(click.defaultPrevented).toBe(true);
	expect(ajax).toHaveBeenCalledWith(
		'GET',
		'/panel/node/create/article/dialog?parent=p',
		expect.objectContaining({ swap: 'innerHTML' }),
	);
	expect(document.querySelector('dialog.cms-modal')).not.toBeNull();
});

it('leaves a click that opens a new tab to the browser', () => {
	const click = new MouseEvent('click', {
		bubbles: true,
		cancelable: true,
		button: 0,
		ctrlKey: true,
	});
	let prevented: boolean | undefined;
	// Records the outcome, then keeps jsdom from navigating.
	const after = (event: Event) => {
		prevented = event.defaultPrevented;
		event.preventDefault();
	};
	window.addEventListener('click', after);
	document.getElementById('new')!.dispatchEvent(click);
	window.removeEventListener('click', after);

	expect(prevented).toBe(false);
	expect(ajax).not.toHaveBeenCalled();
});

it('waits with creating until the required fields are filled', () => {
	document
		.getElementById('new')!
		.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
	const host = document.querySelector<HTMLElement>('dialog.cms-modal .element')!;
	host.innerHTML = `
		<form data-create>
			<div class="sheet"><input name="title" required></div>
			<button type="submit">Create</button>
		</form>`;
	document.dispatchEvent(new CustomEvent('htmx:after:swap', { detail: { ctx: { target: host } } }));
	const input = host.querySelector('input')!;
	const button = host.querySelector('button')!;

	expect(button.disabled).toBe(true);
	expect(document.activeElement).toBe(input);
	input.value = 'Filled';
	input.dispatchEvent(new Event('input', { bubbles: true }));
	expect(button.disabled).toBe(false);
});
