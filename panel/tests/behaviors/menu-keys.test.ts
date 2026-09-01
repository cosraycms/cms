import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install as installKeys } from '../../src/behaviors/menu-keys';
import { install as installTree } from '../../src/behaviors/menu-tree';

let uninstall: Array<() => void> = [];
let submitted: string[] = [];

function row(uid: string): HTMLElement {
	return document.querySelector<HTMLElement>(`[role="treeitem"][data-uid="${uid}"]`)!;
}

function press(key: string, init: KeyboardEventInit = {}): void {
	(document.activeElement ?? document.body).dispatchEvent(
		new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...init }),
	);
}

/** The kebab as the tree renders it: one form per direction. */
function kebab(uid: string, disabled: string[]): string {
	return `<details class="kebab"><summary tabindex="-1"></summary><div class="kebab-menu">
		<a data-menu-add="before" href="/cp/menus/main?before=${uid}"></a>
		<a data-menu-add="after" href="/cp/menus/main?after=${uid}"></a>
		<a data-menu-add="child" href="/cp/menus/main?add=${uid}"></a>
		${['up', 'down', 'in', 'out']
			.map(
				(direction) => `<form method="post" action="/cp/menus/main/item/${uid}/move">
					<input type="hidden" name="direction" value="${direction}" />
					<button type="submit"${disabled.includes(direction) ? ' disabled' : ''}></button>
				</form>`,
			)
			.join('')}
	</div></details>`;
}

beforeEach(() => {
	localStorage.clear();
	submitted = [];
	document.body.innerHTML = `
		<ul class="menu-tree" role="tree" tabindex="-1" data-menu-tree="main">
			<li class="menu-node" role="treeitem" aria-level="1" aria-expanded="true"
				tabindex="-1" data-uid="parent">
				<div class="menu-card">
					<a class="text" tabindex="-1" href="/cp/menus/main?item=parent"></a>
					${kebab('parent', ['up', 'in', 'out'])}
				</div>
				<ul class="menu-children" role="group">
					<li class="menu-node" role="treeitem" aria-level="2" tabindex="-1" data-uid="child">
						<div class="menu-card">${kebab('child', ['up', 'down', 'in'])}</div>
					</li>
				</ul>
			</li>
			<li class="menu-node" role="treeitem" aria-level="1" tabindex="-1" data-uid="second">
				<div class="menu-card">${kebab('second', ['down', 'out'])}</div>
			</li>
		</ul>
		<input id="outside" />
	`;

	for (const form of document.querySelectorAll('form')) {
		form.requestSubmit = (): void => {
			const direction = form.querySelector<HTMLInputElement>('[name="direction"]')!.value;
			submitted.push(`${form.action.split('/item/')[1].replace('/move', '')}:${direction}`);
		};
	}

	uninstall = [installTree(), installKeys()];
});

afterEach(() => {
	for (const off of uninstall) {
		off();
	}

	uninstall = [];
	document.body.innerHTML = '';
	localStorage.clear();
	vi.restoreAllMocks();
});

describe('navigation', () => {
	it('walks the visible rows with the arrows and with j/k', () => {
		row('parent').focus();

		press('ArrowDown');
		expect(document.activeElement).toBe(row('child'));

		press('j');
		expect(document.activeElement).toBe(row('second'));

		press('k');
		expect(document.activeElement).toBe(row('child'));

		press('ArrowUp');
		expect(document.activeElement).toBe(row('parent'));
	});

	it('skips the rows inside a collapsed branch', () => {
		row('parent').focus();

		press('ArrowLeft');
		expect(row('parent').classList.contains('is-collapsed')).toBe(true);

		press('ArrowDown');
		expect(document.activeElement).toBe(row('second'));
	});

	it('folds, then leaves the branch on a second left', () => {
		row('child').focus();

		press('h');
		expect(document.activeElement).toBe(row('parent'));

		press('h');
		expect(row('parent').classList.contains('is-collapsed')).toBe(true);
	});

	it('unfolds, then descends on a second right', () => {
		row('parent').focus();
		press('ArrowLeft');

		press('l');
		expect(row('parent').classList.contains('is-collapsed')).toBe(false);

		press('l');
		expect(document.activeElement).toBe(row('child'));
	});

	it('jumps to the ends', () => {
		row('child').focus();

		press('End');
		expect(document.activeElement).toBe(row('second'));

		press('Home');
		expect(document.activeElement).toBe(row('parent'));
	});

	it('lets the user out again', () => {
		row('parent').focus();

		press('Escape');

		expect(document.activeElement).toBe(document.querySelector('.menu-tree'));
	});
});

describe('moves', () => {
	it('binds the two axes to separate key pairs', () => {
		row('second').focus();

		press('ArrowUp', { altKey: true, code: 'ArrowUp' });
		press('ArrowRight', { altKey: true, code: 'ArrowRight' });
		// macOS composes Option+l into `¬`, so only the physical key is left
		// to recognise the binding by.
		press('¬', { altKey: true, code: 'KeyL' });

		expect(submitted).toEqual(['second:up', 'second:in', 'second:in']);
	});

	it('outdents with Alt+left and with Alt+h', () => {
		row('child').focus();

		press('ArrowLeft', { altKey: true, code: 'ArrowLeft' });
		press('˙', { altKey: true, code: 'KeyH' });

		expect(submitted).toEqual(['child:out', 'child:out']);
	});

	it('leaves Tab to the browser', () => {
		row('child').focus();

		const event = new KeyboardEvent('keydown', {
			key: 'Tab',
			bubbles: true,
			cancelable: true,
		});
		row('child').dispatchEvent(event);

		expect(submitted).toEqual([]);
		expect(event.defaultPrevented).toBe(false);
	});

	it('reorders with Alt and the vim pair on either platform', () => {
		// Windows and Linux deliver the letter, macOS the composed character;
		// only the physical key is common to both.
		row('parent').focus();
		press('j', { altKey: true, code: 'KeyJ' });

		row('second').focus();
		press('˚', { altKey: true, code: 'KeyK' });

		expect(submitted).toEqual(['parent:down', 'second:up']);
	});

	it('does nothing where the kebab says the move is undefined', () => {
		row('parent').focus();

		// The first root row: nothing above it, nothing to climb out of.
		press('ArrowUp', { altKey: true, code: 'ArrowUp' });
		press('ArrowRight', { altKey: true, code: 'ArrowRight' });
		press('ArrowLeft', { altKey: true, code: 'ArrowLeft' });

		expect(submitted).toEqual([]);
	});

	it.each(['Enter', 'e'])('opens the item with %s', (key) => {
		const link = row('parent').querySelector<HTMLAnchorElement>('a.text')!;
		const clicked = vi.fn();
		link.addEventListener('click', clicked);
		row('parent').focus();

		press(key);

		expect(clicked).toHaveBeenCalled();
	});

	it.each([
		['o', 'after'],
		['O', 'before'],
	])("follows the kebab's %s link to the create pane", (key, kind) => {
		const link = row('second').querySelector<HTMLAnchorElement>(`[data-menu-add="${kind}"]`)!;
		const clicked = vi.fn((event: Event) => event.preventDefault());
		link.addEventListener('click', clicked);
		row('second').focus();

		press(key);

		expect(clicked).toHaveBeenCalled();
	});

	it('opens the kebab with a period', () => {
		row('second').focus();

		press('.');

		expect(row('second').querySelector<HTMLDetailsElement>('.kebab')!.open).toBe(true);
	});
});

describe('scope', () => {
	it('stays out of form fields', () => {
		const field = document.querySelector<HTMLInputElement>('#outside')!;
		field.focus();

		press('j');
		press('o');

		expect(submitted).toEqual([]);
		expect(document.activeElement).toBe(field);
	});

	it('leaves modified shortcuts to the browser', () => {
		row('second').focus();

		press('ArrowUp', { metaKey: true, code: 'ArrowUp' });
		press('j', { ctrlKey: true, code: 'KeyJ' });

		expect(submitted).toEqual([]);
		expect(document.activeElement).toBe(row('second'));
	});
});
