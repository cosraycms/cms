import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install, submitMove } from '../../src/behaviors/menu';

let uninstall: (() => void) | null = null;

function click(el: Element): void {
	el.dispatchEvent(new MouseEvent('click', { bubbles: true }));
}

beforeEach(() => {
	vi.useFakeTimers();
	document.body.innerHTML = `
		<form id="menu-drag" method="post" hidden
			data-menu-drag-action="/cp/menus/main/item/__item__/move">
			<input type="hidden" name="parent" value="" />
			<input type="hidden" name="index" value="" />
		</form>
		<ul class="menu-tree" data-menu-list data-parent="">
			<li class="menu-node" data-uid="parent">
				<div class="menu-card">
					<button type="button" data-menu-collapse aria-expanded="true"></button>
				</div>
				<ul class="menu-children" data-menu-list data-parent="parent"><li class="menu-node" data-uid="child"></li></ul>
			</li>
		</ul>
		<form>
			<select data-menu-type>
				<option value="node" selected>node</option>
				<option value="url">url</option>
				<option value="label">label</option>
			</select>
			<div id="node-section" data-menu-section="node"></div>
			<div id="url-section" data-menu-section="url" hidden></div>
			<div id="target-section" data-menu-section="node url asset"></div>
			<div class="control menu-picker" data-menu-picker="nodes" data-menu-picker-url="/cp/reference/nodes?limit=8">
				<input type="hidden" name="node" value="old-uid" data-menu-picker-value />
				<input type="text" data-menu-picker-search value="Old title" />
				<div data-menu-picker-results hidden></div>
			</div>
		</form>
	`;
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
	vi.restoreAllMocks();
	vi.useRealTimers();
});

describe('tree collapse', () => {
	it('toggles the children and the aria state', () => {
		const node = document.querySelector('.menu-node[data-uid="parent"]')!;
		const toggle = node.querySelector('[data-menu-collapse]')!;

		click(toggle);
		expect(node.classList.contains('is-collapsed')).toBe(true);
		expect(toggle.getAttribute('aria-expanded')).toBe('false');

		click(toggle);
		expect(node.classList.contains('is-collapsed')).toBe(false);
		expect(toggle.getAttribute('aria-expanded')).toBe('true');
	});
});

describe('drag submit', () => {
	it('fills the drag form from the drop and submits it', () => {
		const form = document.querySelector<HTMLFormElement>('#menu-drag')!;
		form.requestSubmit = vi.fn();

		const item = document.querySelector<HTMLElement>('.menu-node[data-uid="child"]')!;
		const to = document.querySelector<HTMLElement>('[data-menu-list][data-parent=""]')!;
		const from = document.querySelector<HTMLElement>('[data-menu-list][data-parent="parent"]')!;

		submitMove(item, to, from, 1, 0);

		expect(form.action).toContain('/cp/menus/main/item/child/move');
		expect((form.elements.namedItem('parent') as HTMLInputElement).value).toBe('');
		expect((form.elements.namedItem('index') as HTMLInputElement).value).toBe('1');
		expect(form.requestSubmit).toHaveBeenCalledOnce();
	});

	it('ignores a drop back onto the same slot', () => {
		const form = document.querySelector<HTMLFormElement>('#menu-drag')!;
		form.requestSubmit = vi.fn();

		const item = document.querySelector<HTMLElement>('.menu-node[data-uid="child"]')!;
		const list = document.querySelector<HTMLElement>('[data-menu-list][data-parent="parent"]')!;

		submitMove(item, list, list, 0, 0);

		expect(form.requestSubmit).not.toHaveBeenCalled();
	});
});

describe('type sections', () => {
	it('shows exactly the sections declaring the picked type', () => {
		const select = document.querySelector<HTMLSelectElement>('[data-menu-type]')!;

		select.value = 'url';
		select.dispatchEvent(new Event('change', { bubbles: true }));

		expect(document.getElementById('node-section')!.hidden).toBe(true);
		expect(document.getElementById('url-section')!.hidden).toBe(false);
		expect(document.getElementById('target-section')!.hidden).toBe(false);

		select.value = 'label';
		select.dispatchEvent(new Event('change', { bubbles: true }));

		expect(document.getElementById('url-section')!.hidden).toBe(true);
		expect(document.getElementById('target-section')!.hidden).toBe(true);
	});
});

describe('picker', () => {
	function search(): HTMLInputElement {
		return document.querySelector<HTMLInputElement>('[data-menu-picker-search]')!;
	}

	function value(): HTMLInputElement {
		return document.querySelector<HTMLInputElement>('[data-menu-picker-value]')!;
	}

	function results(): HTMLElement {
		return document.querySelector<HTMLElement>('[data-menu-picker-results]')!;
	}

	function type(text: string): void {
		const input = search();
		input.value = text;
		input.dispatchEvent(new Event('input', { bubbles: true }));
	}

	it('clears the selected uid as soon as the search text changes', () => {
		type('Home');

		expect(value().value).toBe('');
	});

	it('debounces, fetches, and renders picked results into the hidden input', async () => {
		const fetchMock = vi.fn().mockResolvedValue({
			ok: true,
			json: () =>
				Promise.resolve({
					ok: true,
					nodes: [{ uid: 'node-1', title: 'Home', typeLabel: 'Plain Page' }],
					more: false,
				}),
		});
		vi.stubGlobal('fetch', fetchMock);

		type('Ho');
		type('Home');
		await vi.advanceTimersByTimeAsync(300);

		// The second keystroke replaced the first pending search.
		expect(fetchMock).toHaveBeenCalledTimes(1);
		expect(fetchMock.mock.calls[0][0]).toBe('/cp/reference/nodes?limit=8&q=Home');

		const option = results().querySelector<HTMLElement>('[data-menu-picker-option]')!;
		expect(results().hidden).toBe(false);
		expect(option.textContent).toContain('Home');
		expect(option.textContent).toContain('Plain Page');

		click(option);
		expect(value().value).toBe('node-1');
		expect(search().value).toBe('Home');
		expect(results().hidden).toBe(true);
	});

	it('closes open result lists on a click outside the picker', () => {
		results().hidden = false;

		click(document.body);

		expect(results().hidden).toBe(true);
	});

	it('keeps Enter from submitting the form mid-search', () => {
		const event = new KeyboardEvent('keydown', {
			key: 'Enter',
			bubbles: true,
			cancelable: true,
		});
		search().dispatchEvent(event);

		expect(event.defaultPrevented).toBe(true);
	});
});
