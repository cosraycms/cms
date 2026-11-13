import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/chrome';

let uninstall: () => void;

beforeEach(() => {
	uninstall = install();
});

afterEach(() => {
	uninstall();
	document.body.replaceChildren();
});

describe('editor chrome', () => {
	it('empties and hides the preview overlay without removing its anchor', () => {
		document.body.innerHTML = `
			<div id="editor-preview" class="cms-node-preview">
				<button type="button" data-overlay-close><span>Close</span></button>
				<iframe></iframe>
			</div>
		`;
		const overlay = document.querySelector<HTMLElement>('#editor-preview')!;
		document.querySelector<HTMLElement>('[data-overlay-close] span')!.click();
		expect(overlay.isConnected).toBe(true);
		expect(overlay.hidden).toBe(true);
		expect(overlay.childElementCount).toBe(0);
		expect(overlay.hasAttribute('class')).toBe(false);
	});

	it('removes its delegated click listener on uninstall', () => {
		document.body.innerHTML =
			'<div id="editor-preview" class="is-open"><button type="button" data-overlay-close>Close</button></div>';
		const overlay = document.querySelector<HTMLElement>('#editor-preview')!;
		uninstall();
		document.querySelector<HTMLElement>('[data-overlay-close]')!.click();
		expect(overlay.hidden).toBe(false);
		expect(overlay.className).toBe('is-open');
	});
});
