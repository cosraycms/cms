import { afterEach, beforeEach, expect, it } from 'vitest';
import { install } from '../../src/behaviors/tree-rail';

let uninstall: () => void;

function row(uid: string, depth: number, guides: number[] = []): string {
	const spans = guides
		.map((guide) => `<span class="cms-list-guide" data-guide="${guide}"></span>`)
		.join('');

	return `<tr class="cms-list-row" data-uid="${uid}" data-depth="${depth}"><td><div class="cms-list-title">${spans}<a href="#">${uid}</a></div></td></tr>`;
}

beforeEach(() => {
	document.body.innerHTML = `
		<table class="cms-list"><tbody>
			${row('archive', 0)}
			${row('locations', 0)}
			${row('berlin', 1)}
			${row('hamburg', 1)}
			${row('harbour', 2, [1])}
			${row('warehouse', 2, [1])}
			${row('vienna', 1)}
			${row('press', 0)}
		</tbody></table>
		<p id="outside">Outside</p>`;
	uninstall = install();
});

afterEach(() => {
	uninstall();
	document.body.innerHTML = '';
});

function hover(uid: string): void {
	document
		.querySelector(`[data-uid="${uid}"] a`)!
		.dispatchEvent(new Event('pointerover', { bubbles: true }));
}

function lit(): string[] {
	return [...document.querySelectorAll('.is-rail-head, .is-rail-lit, .is-lit')].map((element) => {
		const uid = element.closest('tr')!.dataset.uid;

		return element.classList.contains('cms-list-guide')
			? `${uid}:guide`
			: `${uid}:${element.className.split(' ')[1]}`;
	});
}

it('lights the rail a child hangs from, including the guides that carry it past a nested group', () => {
	hover('berlin');

	expect(lit()).toEqual([
		'locations:is-rail-head',
		'berlin:is-rail-lit',
		'hamburg:is-rail-lit',
		'harbour:guide',
		'warehouse:guide',
		'vienna:is-rail-lit',
	]);
});

it('lights only the level under the pointer, not the rail an open child starts', () => {
	hover('hamburg');
	expect(lit()).not.toContain('hamburg:is-rail-head');

	hover('harbour');
	expect(lit()).toEqual(['hamburg:is-rail-head', 'harbour:is-rail-lit', 'warehouse:is-rail-lit']);
});

it('lights nothing for a top-level row and clears when the pointer leaves the list', () => {
	hover('berlin');
	hover('press');
	expect(lit()).toEqual([]);

	hover('vienna');
	document
		.querySelector('[data-uid="vienna"] a')!
		.dispatchEvent(
			Object.assign(new Event('pointerout', { bubbles: true }), {
				relatedTarget: document.getElementById('outside'),
			}),
		);
	expect(lit()).toEqual([]);
});
