import Sortable from 'sortablejs';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { accepts, drop, fold, install, neighbour } from '../../src/behaviors/collection-order';

vi.mock('sortablejs', () => ({ default: vi.fn() }));

let uninstall: (() => void) | null = null;
let submitted: string[] = [];

/** A tree listing row; `group` marks a row of a manual order. */
function tr(uid: string, depth: number, group?: string, disabled: string[] = []): string {
	const arranged = group === undefined ? '' : ` data-group="${group}"`;
	const moves =
		group === undefined
			? ''
			: ['up', 'down']
					.map(
						(
							direction,
						) => `<form method="post" action="/panel/collection/c/position" data-order-move="${direction}">
							<input type="hidden" name="node" value="${uid}" />
							<input type="hidden" name="move" value="${direction}" />
							<button type="submit"${disabled.includes(direction) ? ' disabled' : ''}></button>
						</form>`,
					)
					.join('');
	const grip = group === undefined ? '' : '<span class="grip" data-order-grip></span>';

	return `<tr class="row" data-uid="${uid}" data-depth="${depth}"${arranged}>
		<td class="cell">${grip}<a class="value link" href="/panel/node/${uid}">${uid}</a></td>
		<td class="cell col-actions"><span class="row-actions">${moves}</span></td>
	</tr>`;
}

function row(uid: string): HTMLElement {
	return document.querySelector<HTMLElement>(`tr[data-uid="${uid}"]`)!;
}

function orderForm(): HTMLFormElement {
	return document.querySelector<HTMLFormElement>('[data-order-form]')!;
}

function field(name: string): string {
	return (orderForm().elements.namedItem(name) as HTMLInputElement).value;
}

function render(moved = ''): void {
	document.body.innerHTML = `
		<div class="cms-collection">
			<form method="post" action="/panel/collection/c/position" hidden data-order-form>
				<input type="hidden" name="node" />
				<input type="hidden" name="before" />
				<input type="hidden" name="after" />
			</form>
			<table><tbody>
				${tr('s1', 0)}
				${tr('a', 1, 's1', ['up'])}
				${tr('b', 1, 's1')}
				${tr('b1', 2, 'b', ['up', 'down'])}
				${tr('c', 1, 's1', ['down'])}
				${tr('s2', 0)}
				${tr('x', 1, 's2', ['up', 'down'])}
			</tbody></table>
		</div>
	`;

	if (moved !== '') {
		row(moved).setAttribute('data-moved', '');
	}

	for (const form of document.querySelectorAll('form')) {
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			const data = new FormData(form);
			submitted.push(
				form.matches('[data-order-form]')
					? `${data.get('node')}:before=${data.get('before')}:after=${data.get('after')}`
					: `${data.get('node')}:${data.get('move')}`,
			);
		});
	}
}

function press(code: string, init: KeyboardEventInit = {}): void {
	(document.activeElement ?? document.body).dispatchEvent(
		new KeyboardEvent('keydown', { code, bubbles: true, cancelable: true, ...init }),
	);
}

beforeEach(() => {
	submitted = [];
	render();
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
	vi.clearAllMocks();
});

describe('drag', () => {
	it('enhances a listing that has grips, dragging only arranged rows by them', async () => {
		await vi.waitFor(() => expect(Sortable).toHaveBeenCalledOnce());

		const [body, options] = vi.mocked(Sortable).mock.calls[0];
		expect(body).toBe(document.querySelector('tbody'));
		expect(options).toMatchObject({ handle: '[data-order-grip]', draggable: 'tr[data-group]' });
	});

	it('only lets a row change places within its own group', () => {
		expect(accepts(row('a'), row('c'))).toBe(true);
		expect(accepts(row('a'), row('b1'))).toBe(false);
		expect(accepts(row('a'), row('x'))).toBe(false);
		expect(accepts(row('a'), row('s2'))).toBe(false);
	});

	it('posts the sibling a row landed after', () => {
		fold(row('b'));
		expect(row('b1').classList.contains('is-drag-folded')).toBe(true);

		row('c').after(row('b'));
		drop(row('b'));

		expect(row('b1').classList.contains('is-drag-folded')).toBe(false);
		expect(submitted).toEqual(['b:before=:after=c']);
	});

	it('posts the sibling a row landed before when it came first', () => {
		fold(row('c'));
		row('a').before(row('c'));

		expect(neighbour(row('c'))).toEqual({ before: 'a' });
		drop(row('c'));
		expect(submitted).toEqual(['c:before=a:after=']);
	});

	it('passes over an expanded sibling’s subtree', () => {
		fold(row('a'));
		row('b1').after(row('a'));

		expect(neighbour(row('a'))).toEqual({ after: 'b' });
	});

	it('posts nothing when the group order did not change', () => {
		fold(row('b'));
		drop(row('b'));

		expect(submitted).toEqual([]);
	});
});

describe('keys', () => {
	it('moves the focused row with Ctrl/Cmd+Shift+Up/Down', () => {
		row('b').querySelector<HTMLElement>('a.value')!.focus();

		press('ArrowDown', { ctrlKey: true, shiftKey: true });
		press('ArrowUp', { metaKey: true, shiftKey: true });

		expect(submitted).toEqual(['b:down', 'b:up']);
	});

	it('claims the keys but submits nothing past the group’s end', () => {
		row('a').querySelector<HTMLElement>('a.value')!.focus();
		const event = new KeyboardEvent('keydown', {
			code: 'ArrowUp',
			ctrlKey: true,
			shiftKey: true,
			bubbles: true,
			cancelable: true,
		});

		document.activeElement!.dispatchEvent(event);

		expect(event.defaultPrevented).toBe(true);
		expect(submitted).toEqual([]);
	});

	it('leaves rows outside a manual order and plain arrows alone', () => {
		row('s1').querySelector<HTMLElement>('a.value')!.focus();
		press('ArrowDown', { ctrlKey: true, shiftKey: true });
		row('b').querySelector<HTMLElement>('a.value')!.focus();
		press('ArrowDown');

		expect(submitted).toEqual([]);
	});
});

describe('focus after a move', () => {
	it('returns to the moved row’s title after a key move', () => {
		row('b').querySelector<HTMLElement>('a.value')!.focus();
		press('ArrowDown', { ctrlKey: true, shiftKey: true });

		render('b');
		document.dispatchEvent(new CustomEvent('htmx:after:swap'));

		expect(document.activeElement).toBe(row('b').querySelector('a.value'));
	});

	it('returns to the same button when a move button was used', () => {
		const button = row('b').querySelector<HTMLButtonElement>('[data-order-move="up"] button')!;
		button.focus();
		button.form!.requestSubmit(button);

		render('b');
		document.dispatchEvent(new CustomEvent('htmx:after:swap'));

		expect(document.activeElement).toBe(row('b').querySelector('[data-order-move="up"] button'));
	});

	it('leaves focus alone after a drag', () => {
		fold(row('c'));
		row('a').before(row('c'));
		drop(row('c'));
		const before = document.activeElement;

		render('c');
		document.dispatchEvent(new CustomEvent('htmx:after:swap'));

		expect(document.activeElement).toBe(before);
	});
});
