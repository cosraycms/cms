import { afterEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/paths';

const LOCALES = ['de', 'en', 'it'];
const CONFIGURED = JSON.stringify([
	{ id: 'de', title: 'Deutsch', fallback: null },
	{ id: 'en', title: 'English', fallback: 'de' },
	{ id: 'it', title: 'Italiano', fallback: 'en' },
]);

let uninstall: (() => void) | undefined;

function mount(values: Record<string, string>, generated: Record<string, string>): void {
	document.body.innerHTML = `
		<form id="node-editor-form">
			<section
				data-paths
				data-locales='${CONFIGURED}'
				data-note-fallback="Uses the {language} path"
				data-note-generated="Generated on save"
				data-note-none="No path">
				<button type="button" data-paths-open>Edit</button>
				<dl>
					${LOCALES.map(
						(locale) => `
							<div data-path-row="${locale}">
								<dt>${locale}</dt>
								<dd><span data-path-value></span><span data-path-note hidden></span></dd>
							</div>`,
					).join('')}
				</dl>
				<dialog data-paths-dialog>
					${LOCALES.map(
						(locale) => `
							<div class="field">
								<input name="paths[${locale}]" data-path-locale="${locale}" value="${values[locale] ?? ''}" />
								<div data-path-suggestion hidden>
									<span data-path-suggestion-value></span>
									<button type="button" data-path-use>Use</button>
								</div>
							</div>`,
					).join('')}
				</dialog>
				<div id="generated-paths" hidden data-paths='${JSON.stringify(generated)}'></div>
			</section>
		</form>
	`;
	uninstall = install();
}

function row(locale: string): { path: string | null; note: string | null } {
	const element = document.querySelector(`[data-path-row="${locale}"]`)!;
	const value = element.querySelector<HTMLElement>('[data-path-value]')!;
	const note = element.querySelector<HTMLElement>('[data-path-note]')!;

	return {
		path: value.hidden ? null : value.textContent,
		note: note.hidden ? null : note.textContent,
	};
}

function input(locale: string): HTMLInputElement {
	return document.querySelector<HTMLInputElement>(`input[data-path-locale="${locale}"]`)!;
}

function suggestion(locale: string): string | null {
	const offer = input(locale)
		.closest('.field')!
		.querySelector<HTMLElement>('[data-path-suggestion]')!;

	return offer.hidden ? null : offer.textContent!.replace('Use', '').trim();
}

describe('paths behavior', () => {
	afterEach(() => {
		uninstall?.();
		uninstall = undefined;
		document.body.innerHTML = '';
	});

	it('lists stored paths and serves empty locales through their fallback chain', () => {
		mount({ de: '/aktuelles/titel', en: '/news/title' }, {});

		expect(row('de')).toEqual({ path: '/aktuelles/titel', note: null });
		expect(row('it')).toEqual({ path: '/news/title', note: 'Uses the English path' });
		expect(input('it').placeholder).toBe('/news/title');

		input('en').value = '';
		input('en').dispatchEvent(new Event('input', { bubbles: true }));

		expect(row('it')).toEqual({ path: '/aktuelles/titel', note: 'Uses the Deutsch path' });
	});

	it('shows the generated paths while every locale is empty', () => {
		mount({}, { de: '/aktuelles/neu', en: '/news/new' });

		expect(row('de')).toEqual({ path: '/aktuelles/neu', note: 'Generated on save' });
		expect(row('it')).toEqual({ path: null, note: 'No path' });
		expect(input('de').placeholder).toBe('/aktuelles/neu');
		expect(suggestion('de')).toBeNull();
	});

	it('offers a generated path that differs from the served one and fills it in', () => {
		mount(
			{ de: '/aktuelles/alt', en: '/news/new' },
			{ de: '/aktuelles/neu', en: '/news/new', it: '/notizie/nuovo' },
		);
		let inputs = 0;
		document.addEventListener('input', () => inputs++, { once: true });

		expect(suggestion('de')).toBe('/aktuelles/neu');
		expect(suggestion('en')).toBeNull();
		expect(suggestion('it')).toBe('/notizie/nuovo');

		input('it').closest('.field')!.querySelector<HTMLElement>('[data-path-use]')!.click();

		expect(input('it').value).toBe('/notizie/nuovo');
		expect(inputs).toBe(1);
		expect(row('it')).toEqual({ path: '/notizie/nuovo', note: null });
		expect(suggestion('it')).toBeNull();
	});

	it('follows the preview after it swaps in', () => {
		mount({ de: '/aktuelles/alt' }, { de: '/aktuelles/alt' });

		expect(suggestion('de')).toBeNull();

		document.getElementById('generated-paths')!.dataset.paths = '{"de":"/aktuelles/neu"}';
		document.dispatchEvent(new CustomEvent('htmx:after:swap'));

		expect(suggestion('de')).toBe('/aktuelles/neu');
	});

	it('opens the dialog from the rail', () => {
		mount({ de: '/aktuelles/titel' }, {});

		document.querySelector<HTMLElement>('[data-paths-open]')!.click();

		expect(document.querySelector<HTMLDialogElement>('dialog[data-paths-dialog]')!.open).toBe(true);
	});
});
