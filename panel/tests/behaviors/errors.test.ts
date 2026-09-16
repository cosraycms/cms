import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install as installErrors } from '../../src/behaviors/errors';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { install as installContentLocales } from '../../src/behaviors/content-locales';

function editor(): void {
	document.body.innerHTML = `
		<form id="node-editor-form">
			<div id="editor-errors" class="errors" tabindex="-1" hidden></div>
			<div class="cms-field" data-field="title">
				<label class="label"><div>Title</div></label>
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
								<div class="cms-field" data-field="trans">
									<div class="control">
										<div class="variant" data-locale="en">
											<input
												name="content[entries][value][zxx][0][fields][trans][value][en]"
												type="text" />
										</div>
										<div class="variant" data-locale="de" hidden>
											<input
												name="content[entries][value][zxx][0][fields][trans][value][de]"
												type="text" />
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="cms-field" data-field="blocks">
				<div class="control">
					<div data-repeater>
						<div class="block" data-repeater-row data-meta-owner data-field="block">
							<button type="button" class="gear" data-meta-open>Settings</button>
							<div class="body">
								<div class="cms-field" data-field="video">
									<div class="control">
										<input name="content[blocks][value][zxx][0][fields][video][value][zxx]" type="text" />
									</div>
								</div>
							</div>
							<dialog data-meta>
								<input name="content[blocks][value][zxx][0][meta][class][zxx]" type="text" />
								<input
									name="content[blocks][value][zxx][0][fields][video][meta][aspectRatioX][zxx]"
									type="number" />
							</dialog>
						</div>
					</div>
				</div>
			</div>
			<aside data-tabs data-inspector>
				<button type="button" data-inspector-open="tab-advanced">Advanced</button>
				<div role="tablist">
					<button type="button" role="tab" id="tab-status" aria-controls="panel-status" aria-selected="true" tabindex="0">Status</button>
					<button type="button" role="tab" id="tab-advanced" aria-controls="panel-advanced" aria-selected="false" tabindex="-1">Advanced</button>
				</div>
				<div id="panel-status" role="tabpanel">
					<section data-paths>
						<button type="button" data-paths-open>Edit</button>
						<dialog data-paths-dialog>
							<div class="field"><input name="paths[en]" data-path-locale="en" type="text" /></div>
						</dialog>
					</section>
				</div>
				<div id="panel-advanced" role="tabpanel" hidden>
					<div class="field"><input name="handle" type="text" /></div>
				</div>
			</aside>
		</form>`;

	for (const field of document.querySelectorAll('.cms-field')) {
		const body = document.createElement('div');
		body.className = 'field-body';
		const control = field.querySelector(':scope > .control')!;
		control.before(body);
		body.append(control);
		const description = field.querySelector(':scope > .description');
		if (description) body.append(description);
	}
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
		uninstall = [installErrors(), installContentLocales(), installRepeater()];
	});

	afterEach(() => {
		uninstall.forEach((cleanup) => cleanup());
		document.body.innerHTML = '';
		localStorage.clear();
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

	it('badges and switches the node control for a native variant', () => {
		const form = document.getElementById('node-editor-form')!;
		form.setAttribute('data-content-locale-scope', '');
		form.setAttribute('data-content-locale', 'en');
		const selector = `<div data-content-locale-control role="radiogroup">
				<button type="button" data-content-locale-option="en" role="radio" aria-checked="true">English</button>
				<button type="button" data-content-locale-option="de" role="radio" aria-checked="false">Deutsch</button>
			</div>`;
		// Twice, as the inspector and its collapsed strip render it.
		form.insertAdjacentHTML('afterbegin', selector + selector);
		respond([{ path: ['content', 'title', 'value', 'de'], message: 'Titel fehlt' }]);

		for (const copy of form.querySelectorAll<HTMLElement>('[data-content-locale-control]')) {
			expect(copy.classList.contains('has-error')).toBe(true);
			expect(copy.dataset.errorLocales).toBe('de');
			expect(
				copy.querySelector('[data-content-locale-option="de"]')?.classList.contains('has-error'),
			).toBe(true);
		}

		const control = form.querySelector<HTMLElement>('[data-content-locale-control]')!;
		const german = control.querySelector<HTMLElement>('[data-content-locale-option="de"]')!;
		document.querySelector<HTMLElement>('[data-error-path]')?.click();
		expect(german.getAttribute('aria-checked')).toBe('true');
		expect(field('title').querySelector<HTMLElement>('[data-locale="de"]')?.hidden).toBe(false);
	});

	it('gets the node locale for an element host from its issue path', () => {
		const form = document.getElementById('node-editor-form')!;
		form.setAttribute('data-content-locale-scope', '');
		form.setAttribute('data-content-locale', 'en');
		form.insertAdjacentHTML(
			'afterbegin',
			'<select data-content-locale-control data-content-locale-select><option value="en">English</option><option value="de">Deutsch</option></select>',
		);
		const box = respond([
			{ path: ['content', 'body', 'value', 'de', 'content', 0], message: 'Body is invalid' },
		]);

		expect(
			form.querySelector<HTMLElement>('[data-content-locale-control]')?.dataset.errorLocales,
		).toBe('de');
		box.querySelector<HTMLElement>('[data-error-path]')?.click();
		expect(form.getAttribute('data-content-locale')).toBe('de');
	});

	it('uses the outer list locale rather than an inner neutral key', () => {
		const form = document.getElementById('node-editor-form')!;
		form.setAttribute('data-content-locale-scope', '');
		form.setAttribute('data-content-locale', 'en');
		form.insertAdjacentHTML(
			'afterbegin',
			'<select data-content-locale-control data-content-locale-select><option value="en">English</option><option value="de">Deutsch</option></select>',
		);
		respond([
			{
				path: ['content', 'blocks', 'value', 'de', 0, 'fields', 'video', 'value', 'zxx'],
				message: 'Video is invalid',
			},
		]);

		expect(
			form.querySelector<HTMLElement>('[data-content-locale-control]')?.dataset.errorLocales,
		).toBe('de');
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

	it('badges the gear of a block for issues in the meta its dialog holds', () => {
		respond([
			{
				path: [
					'content',
					'blocks',
					'value',
					'zxx',
					0,
					'fields',
					'video',
					'meta',
					'aspectRatioX',
					'zxx',
				],
				message: 'Aspect ratio is invalid',
			},
		]);

		expect(field('block').querySelector('.gear')?.classList.contains('has-error')).toBe(true);
		expect(field('block').getAttribute('data-invalid')).toBe('true');
		expect(field('blocks').getAttribute('data-invalid')).toBeNull();
		expect(field('video').getAttribute('data-invalid')).toBeNull();
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
		const form = document.getElementById('node-editor-form')!;
		form.setAttribute('data-content-locale-scope', '');
		form.setAttribute('data-content-locale', 'en');
		form.insertAdjacentHTML(
			'afterbegin',
			'<select data-content-locale-control data-content-locale-select><option value="en">English</option><option value="de">Deutsch</option></select>',
		);
		const box = respond([{ path: ['content', 'title', 'value', 'de'], message: 'Titel fehlt' }]);

		box.querySelector('button')?.click();

		const variant = field('title').querySelector<HTMLElement>('.variant[data-locale="de"]');
		const input = document.querySelector('[name="content[title][value][de]"]');

		expect(variant?.hidden).toBe(false);
		expect(form.getAttribute('data-content-locale')).toBe('de');
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

	it('badges the tab whose hidden panel holds the issue and brings it to the front', () => {
		const inspector = document.querySelector<HTMLElement>('[data-inspector]')!;
		inspector.setAttribute('data-collapsed', '');
		const box = respond([{ path: ['handle'], message: 'Handle is taken' }]);
		const tab = document.getElementById('tab-advanced')!;
		const shortcut = document.querySelector('[data-inspector-open="tab-advanced"]')!;
		const panel = document.getElementById('panel-advanced')!;
		const handle = document.querySelector<HTMLInputElement>('[name="handle"]')!;

		expect(tab.classList.contains('has-error')).toBe(true);
		expect(shortcut.classList.contains('has-error')).toBe(true);
		expect(panel.hidden).toBe(true);

		box.querySelector('button')?.click();

		// Opened for the jump, not remembered as the editor's choice.
		expect(inspector.hasAttribute('data-collapsed')).toBe(false);
		expect(document.cookie).not.toContain('cosray_inspector');
		expect(panel.hidden).toBe(false);
		expect(tab.getAttribute('aria-selected')).toBe('true');
		expect(document.getElementById('tab-status')?.getAttribute('aria-selected')).toBe('false');
		expect(document.activeElement).toBe(handle);

		handle.dispatchEvent(new Event('input', { bubbles: true }));

		expect(tab.classList.contains('has-error')).toBe(false);
		expect(shortcut.classList.contains('has-error')).toBe(false);
	});

	it('opens the paths dialog on summary click', () => {
		let opened = 0;
		const listener = (event: Event): void => {
			if (event.target instanceof Element && event.target.closest('[data-paths-open]')) {
				opened++;
			}
		};
		document.addEventListener('click', listener);

		const box = respond([{ path: ['paths', 'en'], message: 'Path is taken' }]);
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
