import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install as installDirty } from '../../src/behaviors/dirty';
import { install } from '../../src/behaviors/inspector';

let uninstall: Array<() => void> = [];

function editor(collapsed = false): void {
	document.body.innerHTML = `
		<span id="editor-dirty" hidden></span>
		<form id="node-editor-form">
			<aside data-tabs data-inspector ${collapsed ? 'data-collapsed' : ''}>
				<div class="strip">
					<button type="button" data-inspector-expand>Show</button>
					<input type="checkbox" data-inspector-published data-editor-state />
					<button type="button" data-inspector-open="tab-status">Status</button>
					<button type="button" data-inspector-open="tab-advanced">Advanced</button>
				</div>
				<div class="drawer">
					<div role="tablist">
						<button type="button" role="tab" id="tab-status" aria-controls="panel-status" aria-selected="true" tabindex="0">Status</button>
						<button type="button" role="tab" id="tab-advanced" aria-controls="panel-advanced" aria-selected="false" tabindex="-1">Advanced</button>
					</div>
					<button type="button" data-inspector-collapse>Hide</button>
					<div id="panel-status" role="tabpanel">
						<input id="editor-published-switch" type="checkbox" name="published" value="1" />
					</div>
					<div id="panel-advanced" role="tabpanel" hidden></div>
				</div>
			</aside>
		</form>`;
}

function inspector(): HTMLElement {
	return document.querySelector<HTMLElement>('[data-inspector]')!;
}

function click(selector: string): void {
	document.querySelector<HTMLElement>(selector)?.click();
}

beforeEach(() => {
	editor();
	uninstall = [install(), installDirty()];
});

afterEach(() => {
	uninstall.forEach((cleanup) => cleanup());
	document.cookie = 'cosray_inspector=; path=/; max-age=0';
	document.body.innerHTML = '';
});

describe('inspector', () => {
	it('collapses and expands, remembering the choice for the next page', () => {
		click('[data-inspector-collapse]');

		expect(inspector().hasAttribute('data-collapsed')).toBe(true);
		expect(document.cookie).toContain('cosray_inspector=collapsed');
		expect(document.activeElement).toBe(document.querySelector('[data-inspector-expand]'));

		click('[data-inspector-expand]');

		expect(inspector().hasAttribute('data-collapsed')).toBe(false);
		expect(document.cookie).not.toContain('cosray_inspector');
		expect(document.activeElement).toBe(document.getElementById('tab-status'));
	});

	it('opens on the tab a shortcut names', () => {
		uninstall.forEach((cleanup) => cleanup());
		editor(true);
		uninstall = [install()];

		click('[data-inspector-open="tab-advanced"]');

		expect(inspector().hasAttribute('data-collapsed')).toBe(false);
		expect(document.getElementById('tab-advanced')?.getAttribute('aria-selected')).toBe('true');
		expect(document.getElementById('panel-advanced')?.hidden).toBe(false);
		expect(document.activeElement).toBe(document.getElementById('tab-advanced'));
	});

	it('flips the real published switch from the strip and marks the form dirty', () => {
		const copy = document.querySelector<HTMLInputElement>('[data-inspector-published]')!;
		copy.click();

		expect(document.querySelector<HTMLInputElement>('#editor-published-switch')?.checked).toBe(
			true,
		);
		expect(document.getElementById('editor-dirty')?.hidden).toBe(false);
	});

	it('follows the real switch, also when a save replaces it', () => {
		const copy = document.querySelector<HTMLInputElement>('[data-inspector-published]')!;
		document.querySelector<HTMLInputElement>('#editor-published-switch')?.click();

		expect(copy.checked).toBe(true);

		const replacement = document.createElement('input');
		replacement.type = 'checkbox';
		replacement.id = 'editor-published-switch';
		document.getElementById('editor-published-switch')?.replaceWith(replacement);
		document.dispatchEvent(new CustomEvent('htmx:after:swap'));

		expect(copy.checked).toBe(false);
	});
});
