import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/fallbacks';

let teardown: () => void;

function render(values: { en?: string; de?: string; zxx?: string }): void {
	document.body.innerHTML = `
		<form data-content-locale-scope data-content-locales='[
			{"id":"en","title":"English"},
			{"id":"de","title":"Deutsch","fallback":"en"}
		]'>
			<div class="cms-field">
				<div class="control">
					<div class="variant" data-locale="en">
						<input data-fallback-input data-schema-placeholder="Title" value="${values.en ?? ''}">
						<span data-fallback-source data-template="Fallback from {language}" hidden></span>
					</div>
					<div class="variant" data-locale="de">
						<input data-fallback-input data-schema-placeholder="Titel" value="${values.de ?? ''}">
						<span data-fallback-source data-template="Fallback from {language}" hidden></span>
					</div>
					<div class="variant" data-locale="zxx">
						<input data-fallback-input data-schema-placeholder="Shared title" value="${values.zxx ?? ''}">
						<span data-fallback-source data-template="Fallback from {language}" hidden></span>
					</div>
				</div>
			</div>
		</form>`;
}

function renderBlocks(): void {
	document.body.innerHTML = `
		<form data-content-locale-scope data-content-locale="de" data-content-locales='[
			{"id":"en","title":"English"},
			{"id":"de","title":"Deutsch","fallback":"en"}
		]'>
			<select data-content-locale-control data-content-locale-select><option value="en">English</option><option value="de" selected>Deutsch</option></select>
			<div class="cms-field">
				<div class="control">
					<div class="variant" data-locale="en" data-blocks-locale hidden>
						<div class="cms-blocks-editor"><div data-repeater-list>
							<div data-repeater-row><div class="variant" data-locale="en">English block</div><div class="variant" data-locale="de">German block</div></div>
						</div></div>
						<span data-blocks-fallback-source data-template="Fallback from {language}" hidden></span>
					</div>
					<div class="variant" data-locale="de" data-blocks-locale>
						<div class="cms-blocks-editor"><div data-repeater-list></div><button type="button">Add block</button></div>
						<span data-blocks-fallback-source data-template="Fallback from {language}" hidden></span>
					</div>
				</div>
			</div>
		</form>`;
}

function input(locale: string): HTMLInputElement {
	return document.querySelector(`.variant[data-locale="${locale}"] input`)!;
}

function source(locale: string): HTMLElement {
	return document.querySelector(`.variant[data-locale="${locale}"] [data-fallback-source]`)!;
}

describe('native fallback previews', () => {
	beforeEach(() => {
		render({ en: 'Hello' });
		teardown = install();
	});

	afterEach(() => {
		teardown();
		document.body.innerHTML = '';
	});

	it('shows fallback text as a placeholder without changing the target value', () => {
		expect(input('de').value).toBe('');
		expect(input('de').placeholder).toBe('Hello');
		expect(source('de').hidden).toBe(false);
		expect(source('de').textContent).toBe('Fallback from English');
	});

	it('keeps the schema placeholder when the target already has content', () => {
		teardown();
		render({ en: 'Hello', de: 'Hallo' });
		teardown = install();

		expect(input('de').placeholder).toBe('Titel');
		expect(source('de').hidden).toBe(true);
	});

	it('hides the preview while the empty target is focused and restores it on blur', () => {
		input('de').focus();

		expect(input('de').placeholder).toBe('Titel');
		expect(source('de').hidden).toBe(true);
		expect(input('de').value).toBe('');

		input('de').blur();

		expect(input('de').placeholder).toBe('Hello');
		expect(source('de').hidden).toBe(false);
	});

	it('updates previews when the supplying input changes', () => {
		input('en').value = 'Updated';
		input('en').dispatchEvent(new InputEvent('input', { bubbles: true }));

		expect(input('de').placeholder).toBe('Updated');
		expect(input('de').value).toBe('');
	});

	it('uses neutral content only after an empty configured chain', () => {
		teardown();
		render({ zxx: 'Shared' });
		teardown = install();

		expect(input('de').placeholder).toBe('Shared');
		expect(input('de').value).toBe('');
	});

	it('shows an inert source block list without adding rows to the target locale', async () => {
		teardown();
		renderBlocks();
		teardown = install();
		const english = document.querySelector<HTMLElement>('[data-blocks-locale][data-locale="en"]')!;
		const german = document.querySelector<HTMLElement>('[data-blocks-locale][data-locale="de"]')!;

		expect(english.hidden).toBe(false);
		expect(english.inert).toBe(true);
		expect(english.classList.contains('is-fallback-preview')).toBe(true);
		expect(english.querySelector<HTMLElement>('.variant[data-locale="en"]')!.hidden).toBe(false);
		expect(english.querySelector<HTMLElement>('.variant[data-locale="de"]')!.hidden).toBe(true);
		expect(english.querySelector('[data-blocks-fallback-source]')?.textContent).toBe(
			'Fallback from English',
		);
		expect(german.querySelector('[data-repeater-list]')?.children).toHaveLength(0);

		const add = german.querySelector<HTMLButtonElement>('button')!;
		add.focus();
		await Promise.resolve();
		expect(english.hidden).toBe(true);
		add.blur();
		await Promise.resolve();
		expect(english.hidden).toBe(false);

		const row = document.createElement('div');
		row.setAttribute('data-repeater-row', '');
		german.querySelector('[data-repeater-list]')?.append(row);
		row.dispatchEvent(new CustomEvent('repeater:stamp', { bubbles: true }));

		expect(english.hidden).toBe(true);
		expect(english.inert).toBe(false);
		expect(german.hidden).toBe(false);
		expect(german.querySelector('[data-repeater-list]')?.children).toHaveLength(1);
	});
});
