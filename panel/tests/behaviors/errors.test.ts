import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install as installErrors } from '../../src/behaviors/errors';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { install as installTabs } from '../../src/behaviors/tabs';

function editor(): void {
	document.body.innerHTML = `
		<form id="node-editor-form">
			<div id="editor-errors" class="errors" tabindex="-1" hidden></div>
			<div class="cms-field" data-field="title">
				<label class="label"><div>Title</div>
					<span class="locales">
						<button type="button" class="tab active" data-locale-tab="en">EN</button>
						<button type="button" class="tab" data-locale-tab="de">DE</button>
					</span>
				</label>
				<div class="control">
					<div class="variant" data-locale="en">
						<input name="content[title][value][en]" type="text" />
					</div>
					<div class="variant" data-locale="de" hidden>
						<input name="content[title][value][de]" type="text" />
					</div>
				</div>
				<div class="description">About the title</div>
			</div>
			<div class="cms-field" data-field="body">
				<div class="control"><x-host name="content[body][json]"></x-host></div>
			</div>
			<div class="cms-field" data-field="styled">
				<label class="label"><div>Styled</div>
					<button type="button" class="meta-button" data-meta-open>Meta</button>
				</label>
				<div class="control"><input name="content[styled][value][zxx]" type="text" /></div>
				<dialog data-meta>
					<input name="content[styled][meta][cssClass][zxx]" type="text" />
				</dialog>
			</div>
			<div class="cms-field" data-field="entries">
				<div class="control">
					<div data-repeater>
						<div data-repeater-row>
							<button type="button" data-repeater-collapse aria-expanded="false">Row</button>
							<div class="body" data-repeater-body hidden>
								<div class="cms-field" data-field="sub">
									<div class="control">
										<input
											name="content[entries][value][zxx][0][fields][sub][value][zxx]"
											type="text" />
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</form>`;
}

function respond(items: Array<{ path: unknown; message: string }>): HTMLElement {
	const box = document.createElement('div');
	box.id = 'editor-errors';
	box.className = 'errors';
	box.tabIndex = -1;
	box.hidden = items.length === 0;

	const list = document.createElement('ul');

	for (const item of items) {
		const entry = document.createElement('li');
		const button = document.createElement('button');
		button.type = 'button';
		button.setAttribute('data-error-path', JSON.stringify(item.path));
		button.textContent = item.message;
		entry.append(button);
		list.append(entry);
	}

	box.append(list);
	document.getElementById('editor-errors')?.replaceWith(box);
	document.dispatchEvent(new CustomEvent('htmx:after:swap'));

	return box;
}

function field(name: string): HTMLElement {
	const found = document.querySelector(`[data-field="${name}"]`);

	if (!(found instanceof HTMLElement)) {
		throw new Error(`missing fixture field ${name}`);
	}

	return found;
}

describe('errors behavior', () => {
	let uninstall: Array<() => void>;

	beforeEach(() => {
		Element.prototype.scrollIntoView = () => {};
		editor();
		uninstall = [installErrors(), installTabs(), installRepeater()];
	});

	afterEach(() => {
		uninstall.forEach((cleanup) => cleanup());
		document.body.innerHTML = '';
	});

	it('marks the resolved control and its field wrapper', () => {
		respond([{ path: ['content', 'title', 'value', 'en'], message: 'Title is required' }]);

		const wrapper = field('title');
		const input = document.querySelector('[name="content[title][value][en]"]');
		const note = wrapper.querySelector('[data-error-message]');

		expect(wrapper.getAttribute('data-invalid')).toBe('true');
		expect(input?.getAttribute('aria-invalid')).toBe('true');
		expect(note?.textContent).toBe('Title is required');
		expect(input?.getAttribute('aria-describedby')).toBe(note?.id);
	});

	it('focuses the summary box on arrival', () => {
		const box = respond([
			{ path: ['content', 'title', 'value', 'en'], message: 'Title is required' },
		]);

		expect(document.activeElement).toBe(box);
	});

	it('resolves paths inside element values to the host via name prefix', () => {
		respond([{ path: ['content', 'body', 'value', 'de'], message: 'Body is invalid' }]);

		expect(field('body').getAttribute('data-invalid')).toBe('true');
	});

	it('badges the locale tab when the errored variant is hidden', () => {
		respond([{ path: ['content', 'title', 'value', 'de'], message: 'Titel fehlt' }]);

		expect(
			field('title').querySelector('[data-locale-tab="de"]')?.classList.contains('has-error'),
		).toBe(true);
		expect(
			field('title').querySelector('[data-locale-tab="en"]')?.classList.contains('has-error'),
		).toBe(false);
	});

	it('badges the meta button for issues inside the meta dialog', () => {
		respond([
			{ path: ['content', 'styled', 'meta', 'cssClass', 'zxx'], message: 'Class is invalid' },
		]);

		expect(field('styled').querySelector('[data-meta-open]')?.classList.contains('has-error')).toBe(
			true,
		);
		expect(field('styled').getAttribute('data-invalid')).toBe('true');
	});

	it('marks sub-fields inside entries rows', () => {
		respond([
			{
				path: ['content', 'entries', 'value', 'zxx', 0, 'fields', 'sub', 'value', 'zxx'],
				message: 'Sub is required',
			},
		]);

		expect(field('sub').getAttribute('data-invalid')).toBe('true');
	});

	it('leaves unresolvable paths as summary-only entries', () => {
		respond([{ path: ['content', 'gone', 'value', 'zxx'], message: 'Gone is invalid' }]);

		expect(document.querySelectorAll('[data-invalid]').length).toBe(0);
		expect(document.querySelectorAll('[data-error-message]').length).toBe(0);
	});

	it('wipes every mark when the next save response arrives clean', () => {
		respond([{ path: ['content', 'title', 'value', 'en'], message: 'Title is required' }]);
		respond([]);

		expect(document.querySelectorAll('[data-invalid]').length).toBe(0);
		expect(document.querySelectorAll('[data-error-message]').length).toBe(0);
		expect(document.querySelectorAll('[aria-invalid]').length).toBe(0);
	});

	it('ignores swaps that do not replace the summary box', () => {
		const box = respond([
			{ path: ['content', 'title', 'value', 'en'], message: 'Title is required' },
		]);
		const input = document.querySelector<HTMLInputElement>('[name="content[title][value][en]"]');
		input?.focus();

		// e.g. the route-path preview swapping #generated-paths.
		document.dispatchEvent(new CustomEvent('htmx:after:swap'));

		expect(document.activeElement).toBe(input);
		expect(box.isConnected).toBe(true);
		expect(field('title').getAttribute('data-invalid')).toBe('true');
	});

	it('reveals the hidden locale variant and focuses the control on summary click', () => {
		const box = respond([{ path: ['content', 'title', 'value', 'de'], message: 'Titel fehlt' }]);

		box.querySelector('button')?.click();

		const variant = field('title').querySelector<HTMLElement>('.variant[data-locale="de"]');
		const input = document.querySelector('[name="content[title][value][de]"]');

		expect(variant?.hidden).toBe(false);
		expect(
			field('title').querySelector('[data-locale-tab="de"]')?.classList.contains('active'),
		).toBe(true);
		expect(document.activeElement).toBe(input);
	});

	it('expands the collapsed entries row on summary click', () => {
		const box = respond([
			{
				path: ['content', 'entries', 'value', 'zxx', 0, 'fields', 'sub', 'value', 'zxx'],
				message: 'Sub is required',
			},
		]);

		box.querySelector('button')?.click();

		const body = document.querySelector<HTMLElement>('[data-repeater-body]');

		expect(body?.hidden).toBe(false);
		expect(document.querySelector('[data-repeater-collapse]')?.getAttribute('aria-expanded')).toBe(
			'true',
		);
		expect(document.activeElement).toBe(
			document.querySelector('[name="content[entries][value][zxx][0][fields][sub][value][zxx]"]'),
		);
	});

	it('opens the meta dialog on summary click', () => {
		let opened = 0;
		const listener = (event: Event): void => {
			if (event.target instanceof Element && event.target.closest('[data-meta-open]')) {
				opened++;
			}
		};
		document.addEventListener('click', listener);

		const box = respond([
			{ path: ['content', 'styled', 'meta', 'cssClass', 'zxx'], message: 'Class is invalid' },
		]);
		box.querySelector('button')?.click();

		document.removeEventListener('click', listener);
		expect(opened).toBe(1);
	});

	it('clears the marks of a field the user edits, keeping the summary', () => {
		const box = respond([
			{ path: ['content', 'title', 'value', 'en'], message: 'Title is required' },
			{ path: ['content', 'styled', 'value', 'zxx'], message: 'Styled is invalid' },
		]);

		const input = document.querySelector('[name="content[title][value][en]"]');
		input?.dispatchEvent(new Event('input', { bubbles: true }));

		expect(field('title').hasAttribute('data-invalid')).toBe(false);
		expect(field('title').querySelector('[data-error-message]')).toBeNull();
		expect(input?.hasAttribute('aria-invalid')).toBe(false);
		// The untouched field and the summary stay.
		expect(field('styled').getAttribute('data-invalid')).toBe('true');
		expect(box.hidden).toBe(false);
		expect(box.querySelectorAll('[data-error-path]').length).toBe(2);
	});
});
