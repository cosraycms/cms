import { afterEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/blocks-width';

let uninstall: () => void = () => {};

function editor(wide = false): void {
	document.body.innerHTML = `
		<div class="page cms-node" ${wide ? 'data-wide-blocks' : ''}>
			<button type="button" data-blocks-width aria-pressed="false">Full width</button>
			<button type="button" data-blocks-width aria-pressed="false">Full width</button>
		</div>`;
}

function wide(): boolean {
	return document.querySelector('.cms-node')!.hasAttribute('data-wide-blocks');
}

function toggles(): HTMLButtonElement[] {
	return [...document.querySelectorAll<HTMLButtonElement>('[data-blocks-width]')];
}

function pressed(): Array<string | null> {
	return toggles().map((toggle) => toggle.getAttribute('aria-pressed'));
}

afterEach(() => {
	uninstall();
	document.cookie = 'cosray_blocks=; path=/; max-age=0';
	document.body.innerHTML = '';
});

describe('blocks width', () => {
	it('widens every grid field at once and remembers the choice for the next page', () => {
		editor();
		uninstall = install();

		toggles()[0]!.click();

		expect(wide()).toBe(true);
		expect(document.cookie).toContain('cosray_blocks=wide');
		expect(pressed()).toEqual(['true', 'true']);

		toggles()[1]!.click();

		expect(wide()).toBe(false);
		expect(document.cookie).not.toContain('cosray_blocks');
		expect(pressed()).toEqual(['false', 'false']);
	});

	it('reports the width an editor arrives in', () => {
		uninstall = install();
		editor(true);
		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(pressed()).toEqual(['true', 'true']);
	});
});
