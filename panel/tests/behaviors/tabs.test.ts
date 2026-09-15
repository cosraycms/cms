import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install, revealTab } from '../../src/behaviors/tabs';

let uninstall: (() => void) | null = null;

function block(): void {
	document.body.innerHTML = `
		<div data-tabs>
			<div role="tablist">
				<button type="button" role="tab" id="tab-a" aria-controls="panel-a" aria-selected="true" tabindex="0">A</button>
				<button type="button" role="tab" id="tab-b" aria-controls="panel-b" aria-selected="false" tabindex="-1">B</button>
				<button type="button" role="tab" id="tab-c" aria-controls="panel-c" aria-selected="false" tabindex="-1">C</button>
			</div>
			<div id="panel-a" role="tabpanel"><input id="in-a" /></div>
			<div id="panel-b" role="tabpanel" hidden><input id="in-b" /></div>
			<div id="panel-c" role="tabpanel" hidden></div>
		</div>
		<div role="tablist">
			<button type="button" role="tab" id="tab-x" aria-selected="false" tabindex="0">X</button>
		</div>
	`;
}

function tab(id: string): HTMLButtonElement {
	return document.getElementById(id) as HTMLButtonElement;
}

function selected(): string[] {
	return Array.from(document.querySelectorAll('[role="tab"][aria-selected="true"]'), (el) => el.id);
}

function visible(): string[] {
	return Array.from(document.querySelectorAll<HTMLElement>('[role="tabpanel"]'))
		.filter((panel) => !panel.hidden)
		.map((panel) => panel.id);
}

function press(target: HTMLElement, key: string): void {
	target.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));
}

beforeEach(() => {
	block();
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
});

describe('tabs', () => {
	it('shows the clicked tab and hides the other panels', () => {
		tab('tab-b').click();

		expect(selected()).toEqual(['tab-b']);
		expect(visible()).toEqual(['panel-b']);
		expect(tab('tab-b').tabIndex).toBe(0);
		expect(tab('tab-a').tabIndex).toBe(-1);
	});

	it('moves with the arrow, Home and End keys as one tab stop', () => {
		tab('tab-a').focus();

		press(tab('tab-a'), 'ArrowRight');
		expect(selected()).toEqual(['tab-b']);
		expect(document.activeElement).toBe(tab('tab-b'));

		press(tab('tab-b'), 'ArrowLeft');
		press(tab('tab-a'), 'ArrowLeft');
		expect(selected()).toEqual(['tab-c']);
		expect(visible()).toEqual(['panel-c']);

		press(tab('tab-c'), 'Home');
		expect(selected()).toEqual(['tab-a']);

		press(tab('tab-a'), 'End');
		expect(selected()).toEqual(['tab-c']);
		expect(document.activeElement).toBe(tab('tab-c'));
	});

	it('leaves a tablist outside a data-tabs block alone', () => {
		tab('tab-x').click();
		press(tab('tab-x'), 'ArrowRight');

		expect(tab('tab-x').getAttribute('aria-selected')).toBe('false');
		expect(selected()).toEqual(['tab-a']);
	});

	it('reveals the tab holding a control', () => {
		revealTab(document.getElementById('in-b')!);

		expect(selected()).toEqual(['tab-b']);
		expect(visible()).toEqual(['panel-b']);

		revealTab(document.getElementById('in-b')!);
		expect(selected()).toEqual(['tab-b']);
	});
});
