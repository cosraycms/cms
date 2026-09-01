import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/menu-tree';

let uninstall: (() => void) | null = null;

function click(el: Element): void {
	el.dispatchEvent(new MouseEvent('click', { bubbles: true }));
}

function row(uid: string): HTMLElement {
	return document.querySelector<HTMLElement>(`[role="treeitem"][data-uid="${uid}"]`)!;
}

function markup(selected = ''): string {
	const card = (uid: string): string =>
		`<div class="menu-card${uid === selected ? ' is-selected' : ''}">
			<button type="button" data-menu-collapse tabindex="-1"></button>
			<a class="text" tabindex="-1" href="#"></a>
		</div>`;

	return `
		<button id="outside" type="button"></button>
		<ul class="menu-tree" role="tree" tabindex="-1" data-menu-tree="main">
			<li class="menu-node" role="treeitem" aria-level="1" aria-expanded="true"
				tabindex="-1" data-uid="parent">
				${card('parent')}
				<ul class="menu-children" role="group">
					<li class="menu-node" role="treeitem" aria-level="2" tabindex="-1" data-uid="child">
						${card('child')}
					</li>
				</ul>
			</li>
			<li class="menu-node" role="treeitem" aria-level="1" tabindex="-1" data-uid="second">
				${card('second')}
			</li>
		</ul>
	`;
}

beforeEach(() => {
	localStorage.clear();
	document.body.innerHTML = markup();
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
	localStorage.clear();
});

describe('collapse', () => {
	it('toggles the row and its aria state', () => {
		const parent = row('parent');

		click(parent.querySelector('[data-menu-collapse]')!);
		expect(parent.classList.contains('is-collapsed')).toBe(true);
		expect(parent.getAttribute('aria-expanded')).toBe('false');

		click(parent.querySelector('[data-menu-collapse]')!);
		expect(parent.classList.contains('is-collapsed')).toBe(false);
		expect(parent.getAttribute('aria-expanded')).toBe('true');
	});

	it('leaves a row without children alone', () => {
		const leaf = row('second');

		click(leaf.querySelector('.menu-card')!);

		expect(leaf.classList.contains('is-collapsed')).toBe(false);
		expect(leaf.getAttribute('aria-expanded')).toBeNull();
	});

	it('survives the re-render every move triggers', () => {
		click(row('parent').querySelector('[data-menu-collapse]')!);
		expect(localStorage.getItem('cosray:menu-collapsed:main')).toBe('["parent"]');

		// What an htmx swap leaves behind: fresh markup, fully expanded.
		document.body.innerHTML = markup();
		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(row('parent').classList.contains('is-collapsed')).toBe(true);
		expect(row('parent').getAttribute('aria-expanded')).toBe('false');
	});

	it('opens the tree fully when storage is unusable', () => {
		localStorage.setItem('cosray:menu-collapsed:main', 'not json');

		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(row('parent').classList.contains('is-collapsed')).toBe(false);
	});
});

describe('roving tabindex', () => {
	it('makes exactly one row tabbable', () => {
		const tabbable = [...document.querySelectorAll('[role="treeitem"][tabindex="0"]')];

		expect(tabbable).toHaveLength(1);
		expect(tabbable[0]).toBe(row('parent'));
	});

	it('starts on the row a move redirect selected', () => {
		document.body.innerHTML = markup('child');
		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(row('child').tabIndex).toBe(0);
		expect(row('parent').tabIndex).toBe(-1);
	});

	it('follows the selected row only when the tree held focus', () => {
		expect(document.activeElement).not.toBe(row('parent'));

		row('parent').focus();
		document.body.innerHTML = markup('second');
		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(document.activeElement).toBe(row('second'));
	});

	it('survives the focused row being torn out by the swap', () => {
		row('parent').focus();

		// What a move does to the row it was triggered from: removed, with no
		// element to hand the focus to. That must not read as leaving the tree,
		// or a second move in a row would be impossible.
		row('parent').dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: null }));
		document.body.innerHTML = markup('second');
		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(document.activeElement).toBe(row('second'));
	});

	it('lets go once the user works outside the tree', () => {
		row('parent').focus();
		document.querySelector('#outside')!.dispatchEvent(new MouseEvent('click', { bubbles: true }));

		document.body.innerHTML = markup('second');
		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(document.activeElement).not.toBe(row('second'));
		expect(row('second').tabIndex).toBe(0);
	});
});
