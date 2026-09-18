import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/scroll';

let uninstall: () => void;

beforeEach(() => {
	uninstall = install();
});

afterEach(() => {
	uninstall();
	document.body.replaceChildren();
});

describe('collection scrolling', () => {
	function toggle(): { main: HTMLElement; ctx: { sourceElement: unknown } } {
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
		const list = document.querySelector<HTMLElement>('.cms-collection .scroll')!;
		const source = document.querySelector<HTMLElement>('[data-collection-toggle]')!;
		list.scrollLeft = 31;
		list.scrollTop = 420;
		const ctx = { sourceElement: source };

		source.dispatchEvent(
			new CustomEvent('htmx:before:request', { bubbles: true, detail: { ctx } }),
		);
		main.innerHTML = '<div class="cms-collection"><div class="scroll"></div></div>';

		return { main, ctx };
	}

	it('keeps the list position when opening or closing children', () => {
		const { main, ctx } = toggle();

		main.dispatchEvent(new CustomEvent('htmx:finally:swap', { bubbles: true, detail: { ctx } }));

		const list = document.querySelector<HTMLElement>('.cms-collection .scroll')!;
		expect(list.scrollLeft).toBe(31);
		expect(list.scrollTop).toBe(420);
	});

	it('leaves a list alone after a swap that came from anywhere else', () => {
		document.body.innerHTML = `
			<main id="main"><div class="cms-collection"><div class="scroll"></div></div></main>
		`;
		const main = document.getElementById('main')!;
		const list = document.querySelector<HTMLElement>('.cms-collection .scroll')!;
		list.scrollTop = 96;
		const ctx = { sourceElement: main };

		main.dispatchEvent(new CustomEvent('htmx:before:request', { bubbles: true, detail: { ctx } }));
		main.dispatchEvent(new CustomEvent('htmx:finally:swap', { bubbles: true, detail: { ctx } }));

		expect(list.scrollTop).toBe(96);
	});
});
