import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/scroll';

type Callback = (entries: ResizeObserverEntry[]) => void;

let uninstall: () => void;
let observed: Element[];
let notify: Callback | undefined;

beforeEach(() => {
	observed = [];
	notify = undefined;
	vi.stubGlobal(
		'ResizeObserver',
		class {
			constructor(callback: Callback) {
				notify = callback;
			}

			observe(target: Element): void {
				observed.push(target);
			}

			disconnect(): void {
				observed = [];
			}
		},
	);
	vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
	uninstall = install();
});

afterEach(() => {
	uninstall();
	vi.restoreAllMocks();
	vi.unstubAllGlobals();
	document.body.replaceChildren();
});

function swap(target: unknown): void {
	document.dispatchEvent(new CustomEvent('htmx:after:swap', { detail: { ctx: { target } } }));
}

describe('document scrolling', () => {
	it('starts a navigated page at the top', () => {
		document.body.innerHTML = '<div id="frame"><main id="main"></main></div>';
		swap(document.getElementById('main'));
		swap('#frame');
		expect(window.scrollTo).toHaveBeenCalledTimes(2);
		expect(window.scrollTo).toHaveBeenCalledWith(0, 0);
	});

	it('leaves the position alone after a partial swap or a history restore', () => {
		document.body.innerHTML = `
			<div class="cms-shell"><main id="main"><div id="paths"></div></main></div>
		`;
		swap(document.getElementById('paths'));
		swap(document.querySelector('.cms-shell'));
		swap(undefined);
		expect(window.scrollTo).not.toHaveBeenCalled();
	});

	it('keeps the collection position when opening or closing children', () => {
		vi.spyOn(window, 'scrollX', 'get').mockReturnValue(17);
		vi.spyOn(window, 'scrollY', 'get').mockReturnValue(240);
		document.body.innerHTML = `
			<main id="main">
				<div class="cms-collection">
					<div class="scroll">
						<a href="?open=parent" data-collection-toggle>Open</a>
					</div>
				</div>
			</main>
		`;
		const main = document.getElementById('main')!;
		const oldList = document.querySelector<HTMLElement>('.cms-collection .scroll')!;
		const toggle = document.querySelector<HTMLElement>('[data-collection-toggle]')!;
		oldList.scrollLeft = 31;
		oldList.scrollTop = 420;
		const ctx = { sourceElement: toggle, target: main };

		toggle.dispatchEvent(
			new CustomEvent('htmx:before:request', { bubbles: true, detail: { ctx } }),
		);
		main.innerHTML = '<div class="cms-collection"><div class="scroll"></div></div>';
		ctx.sourceElement = main;
		main.dispatchEvent(new CustomEvent('htmx:after:swap', { bubbles: true, detail: { ctx } }));
		main.dispatchEvent(new CustomEvent('htmx:finally:swap', { bubbles: true, detail: { ctx } }));

		const newList = document.querySelector<HTMLElement>('.cms-collection .scroll')!;
		expect(newList.scrollLeft).toBe(31);
		expect(newList.scrollTop).toBe(420);
		expect(window.scrollTo).toHaveBeenCalledOnce();
		expect(window.scrollTo).toHaveBeenCalledWith(17, 240);
	});

	it('measures the page head into the sticky offset of its page', () => {
		document.body.innerHTML = `
			<main id="main"><div class="page cms-node"><header class="head"></header></div></main>
		`;
		swap(document.getElementById('main'));
		const head = document.querySelector('.head')!;
		expect(observed).toEqual([head]);
		notify?.([
			{ target: head, borderBoxSize: [{ blockSize: 76, inlineSize: 800 }] },
		] as unknown as ResizeObserverEntry[]);
		const page = document.querySelector<HTMLElement>('.page')!;
		expect(page.style.getPropertyValue('--cms-head-height')).toBe('76px');
	});
});
