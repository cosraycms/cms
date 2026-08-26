import { afterEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/when';

let uninstall: (() => void) | null = null;

function setup(html: string): void {
	document.body.innerHTML = html;
	uninstall = install();
}

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
});

function wrapper(): HTMLElement {
	const el = document.querySelector<HTMLElement>('.cms-field[data-when]');

	if (!el) {
		throw new Error('wrapper missing');
	}

	return el;
}

const CONDITION = '{"field":"flag","op":"truthy","value":null}';

describe('when behavior', () => {
	it('hides an inactive field on install and suspends required', () => {
		setup(`
			<form id="node-editor-form">
				<div class="cms-field" data-when='${CONDITION}'>
					<input name="content[dependent][value][zxx]" required>
				</div>
				<input type="checkbox" name="content[flag][value][zxx]" value="1">
			</form>
		`);

		const input = wrapper().querySelector('input');

		expect(wrapper().hidden).toBe(true);
		expect(input?.hasAttribute('required')).toBe(false);
		expect(input?.hasAttribute('data-when-required')).toBe(true);
	});

	it('shows the field and restores required when the condition activates', () => {
		setup(`
			<form id="node-editor-form">
				<div class="cms-field" data-when='${CONDITION}'>
					<input name="content[dependent][value][zxx]" required>
				</div>
				<input type="checkbox" name="content[flag][value][zxx]" value="1">
			</form>
		`);

		const box = document.querySelector<HTMLInputElement>('input[type=checkbox]');

		if (!box) {
			throw new Error('checkbox missing');
		}

		box.checked = true;
		box.dispatchEvent(new Event('change', { bubbles: true }));

		const input = wrapper().querySelector('input');

		expect(wrapper().hidden).toBe(false);
		expect(input?.hasAttribute('required')).toBe(true);
		expect(input?.hasAttribute('data-when-required')).toBe(false);
	});

	it('lets the checkbox win over its presence marker (last form entry wins)', () => {
		setup(`
			<form id="node-editor-form">
				<div class="cms-field" data-when='${CONDITION}'>
					<input name="content[dependent][value][zxx]">
				</div>
				<input type="hidden" name="content[flag][value][zxx]" value="">
				<input type="checkbox" name="content[flag][value][zxx]" value="1" checked>
			</form>
		`);

		expect(wrapper().hidden).toBe(false);
	});

	it('ignores edits outside the editor form', () => {
		setup(`
			<form id="node-editor-form">
				<div class="cms-field" data-when='${CONDITION}'>
					<input name="content[dependent][value][zxx]">
				</div>
				<input type="checkbox" name="content[flag][value][zxx]" value="1">
			</form>
			<form id="other"><input name="unrelated"></form>
		`);

		const box = document.querySelector<HTMLInputElement>('input[type=checkbox]');
		const unrelated = document.querySelector<HTMLInputElement>('#other input');

		if (!box || !unrelated) {
			throw new Error('inputs missing');
		}

		// Checking without an event would activate on the next apply; an
		// edit in an unrelated form must not be that apply.
		box.checked = true;
		unrelated.dispatchEvent(new Event('input', { bubbles: true }));

		expect(wrapper().hidden).toBe(true);
	});

	it('leaves a wrapper with malformed data-when visible', () => {
		setup(`
			<form id="node-editor-form">
				<div class="cms-field" data-when='not json'>
					<input name="content[dependent][value][zxx]">
				</div>
			</form>
		`);

		expect(wrapper().hidden).toBe(false);
	});

	it('hides a fieldset once all of its fields are hidden', () => {
		setup(`
			<form id="node-editor-form">
				<fieldset class="cms-fieldset">
					<div class="cms-field" data-when='${CONDITION}'>
						<input name="content[dependent][value][zxx]">
					</div>
				</fieldset>
				<input type="checkbox" name="content[flag][value][zxx]" value="1">
			</form>
		`);

		const fieldset = document.querySelector<HTMLFieldSetElement>('.cms-fieldset');

		expect(fieldset?.hidden).toBe(true);

		const box = document.querySelector<HTMLInputElement>('input[type=checkbox]');

		if (!box) {
			throw new Error('checkbox missing');
		}

		box.checked = true;
		box.dispatchEvent(new Event('change', { bubbles: true }));

		expect(fieldset?.hidden).toBe(false);
	});
});
