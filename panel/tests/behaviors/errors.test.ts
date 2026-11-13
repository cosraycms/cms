import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install as installErrors } from '../../src/behaviors/errors';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { install as installContentLocales } from '../../src/behaviors/content-locales';

function editor(): void {
	document.body.innerHTML = `
		<div id="editor-problems" hidden>
			<button type="button" popovertarget="editor-problems-menu"><span class="cms-node-problems-count"></span></button>
			<div id="editor-problems-menu" popover="auto" data-action-menu><div class="cms-node-problems-entries"></div></div>
		</div>
		<form id="node-editor-form">
			<div id="editor-errors" class="cms-errors" tabindex="-1" hidden></div>
			<div class="cms-field" data-field="title">
				<label class="cms-field-label"><div><span class="cms-field-name">Title</span></div></label>
				<div class="cms-field-control">
					<div class="cms-locale-variant" data-locale="en">
						<input name="content[title][value][en]" type="text" />
					</div>
					<div class="cms-locale-variant" data-locale="de" hidden>
						<input name="content[title][value][de]" type="text" />
					</div>
				</div>
				<div class="cms-field-description">About the title</div>
			</div>
			<div class="cms-field" data-field="body">
				<div class="cms-field-control"><x-host name="content[body][json]"></x-host></div>
			</div>
			<div class="cms-field" data-field="styled">
				<label class="cms-field-label"><div><span class="cms-field-name">Styled</span></div>
					<button type="button" class="cms-field-button" data-dialog-open="meta">Meta</button>
				</label>
				<div class="cms-field-control"><input name="content[styled][value][zxx]" type="text" /></div>
				<dialog data-dialog="meta">
					<input name="content[styled][meta][cssClass][zxx]" type="text" />
				</dialog>
			</div>
			<div class="cms-field" data-field="entries">
				<div class="cms-field-control">
					<div data-repeater>
						<div data-repeater-row>
							<button type="button" data-repeater-collapse aria-expanded="false">Row</button>
							<div class="body" data-repeater-body hidden>
								<div class="cms-field" data-field="sub">
									<div class="cms-field-control">
										<input
											name="content[entries][value][zxx][0][fields][sub][value][zxx]"
											type="text" />
									</div>
								</div>
								<div class="cms-field" data-field="trans">
									<div class="cms-field-control">
										<div class="cms-locale-variant" data-locale="en">
											<input
												name="content[entries][value][zxx][0][fields][trans][value][en]"
												type="text" />
										</div>
										<div class="cms-locale-variant" data-locale="de" hidden>
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
				<div class="cms-field-control">
					<div data-repeater>
						<div class="block" data-repeater-row data-meta-owner data-field="block">
							<button type="button" class="gear" data-dialog-open="meta">Settings</button>
							<div class="body">
								<div class="cms-field" data-field="video">
									<div class="cms-field-control">
										<input name="content[blocks][value][zxx][0][fields][video][value][zxx]" type="text" />
									</div>
								</div>
							</div>
							<dialog data-dialog="meta">
								<input name="content[blocks][value][zxx][0][meta][class][zxx]" type="text" />
								<input
									name="content[blocks][value][zxx][0][fields][video][meta][aspectRatioX][zxx]"
									type="number" />
								<div class="cms-field" data-field="style">
									<div class="cms-field-control">
										<select name="content[blocks][value][zxx][0][fields][style][value][zxx]"></select>
									</div>
								</div>
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
						<button type="button" data-dialog-open="paths">Edit</button>
						<dialog data-dialog="paths">
							<div class="cms-inspector-field"><input name="paths[en]" data-path-locale="en" type="text" /></div>
						</dialog>
					</section>
				</div>
				<div id="panel-advanced" role="tabpanel" hidden>
					<div class="cms-inspector-field"><input name="handle" type="text" /></div>
				</div>
			</aside>
		</form>`;

	for (const field of document.querySelectorAll('.cms-field')) {
		const body = document.createElement('div');
		body.className = 'cms-field-body';
		const control = field.querySelector(':scope > .cms-field-control')!;
		control.before(body);
		body.append(control);
		const description = field.querySelector(':scope > .cms-field-description');
		if (description) body.append(description);
	}
}

type Item = { path: unknown; message: string; code?: string };

function respond(items: Item[]): HTMLElement {
	const box = document.createElement('div');
	box.id = 'editor-errors';
	box.className = 'cms-errors';
	box.tabIndex = -1;
	box.hidden = items.length === 0;
	box.innerHTML =
		'<span class="cms-errors-mark">!</span><div class="cms-errors-summary"><p class="cms-errors-title">Not saved</p></div>';

	const list = document.createElement('ul');
	list.className = 'cms-errors-issues';

	for (const item of items) {
		const entry = document.createElement('li');

		if (item.path === null) {
			entry.textContent = item.message;
		} else {
			const button = document.createElement('button');
			button.type = 'button';
			button.setAttribute('data-error-path', JSON.stringify(item.path));
			button.setAttribute('data-error-code', item.code ?? 'rule.minlen');
			button.textContent = item.message;
			entry.append(button);
		}

		list.append(entry);
	}

	box.querySelector('.cms-errors-summary')!.append(list);
	document.getElementById('editor-errors')?.replaceWith(box);
	document.dispatchEvent(new CustomEvent('htmx:after:swap'));

	return box;
}

function chips(box: HTMLElement): string[] {
	return Array.from(box.querySelectorAll('.cms-errors-chips [data-error-jump]'), (chip) =>
		(chip.textContent ?? '').trim(),
	);
}

function input(name: string): HTMLInputElement {
	return document.querySelector<HTMLInputElement>(`[name="${name.replace(/[[\]]/g, '\\$&')}"]`)!;
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
		vi.unstubAllGlobals();
		document.body.innerHTML = '';
		document.cookie = 'cosray_panel=; path=/panel; max-age=0';
		history.replaceState(null, '', '/');
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

	it('focuses the first field on arrival', () => {
		respond([
			{ path: ['content', 'title', 'value', 'en'], message: 'Title is required' },
			{ path: ['content', 'styled', 'value', 'zxx'], message: 'Styled is invalid' },
		]);

		expect(document.activeElement).toBe(input('content[title][value][en]'));
	});

	it('focuses the summary box when the first field is out of sight', () => {
		const box = respond([{ path: ['content', 'title', 'value', 'de'], message: 'Titel fehlt' }]);

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
		document.querySelector<HTMLElement>('[data-error-jump]')?.click();
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
		box.querySelector<HTMLElement>('[data-error-jump]')?.click();
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

		expect(
			field('styled').querySelector('[data-dialog-open="meta"]')?.classList.contains('has-error'),
		).toBe(true);
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

	it('badges the gear and opens the dialog for a block field edited in its settings', () => {
		const dialog = field('block').querySelector('dialog')!;
		let opened = 0;
		const listener = (event: Event): void => {
			if (event.target instanceof Element && event.target.closest('.gear')) {
				opened++;
			}
		};
		document.addEventListener('click', listener);

		const box = respond([
			{
				path: ['content', 'blocks', 'value', 'zxx', 0, 'fields', 'style', 'value', 'zxx'],
				message: 'Style is invalid',
			},
		]);

		expect(field('block').querySelector('.gear')?.classList.contains('has-error')).toBe(true);
		expect(dialog.querySelector('select')?.getAttribute('aria-invalid')).toBe('true');

		box.querySelector<HTMLElement>('[data-error-jump]')?.click();
		document.removeEventListener('click', listener);
		expect(opened).toBe(1);
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

	it('lists unresolvable paths and pathless issues in the summary only', () => {
		const box = respond([
			{ path: ['content', 'gone', 'value', 'zxx'], message: 'Gone is invalid' },
			{ path: null, message: 'Something else failed' },
		]);

		expect(document.querySelectorAll('[data-invalid]').length).toBe(0);
		expect(document.querySelectorAll('[data-error-message]').length).toBe(0);
		expect(
			Array.from(box.querySelectorAll('.cms-errors-general li'), (item) => item.textContent),
		).toEqual(['Gone is invalid', 'Something else failed']);
		expect(document.querySelector('#editor-problems .cms-node-problems-count')?.textContent).toBe(
			'editor:problems-plural',
		);
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

		box.querySelector<HTMLElement>('[data-error-jump]')?.click();

		const variant = field('title').querySelector<HTMLElement>(
			'.cms-locale-variant[data-locale="de"]',
		);
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

		box.querySelector<HTMLElement>('[data-error-jump]')?.click();

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
			if (event.target instanceof Element && event.target.closest('[data-dialog-open="meta"]')) {
				opened++;
			}
		};
		document.addEventListener('click', listener);

		const box = respond([
			{ path: ['content', 'styled', 'meta', 'cssClass', 'zxx'], message: 'Class is invalid' },
		]);
		box.querySelector<HTMLElement>('[data-error-jump]')?.click();

		document.removeEventListener('click', listener);
		expect(opened).toBe(1);
	});

	it('badges the tab whose hidden panel holds the issue and brings it to the front', () => {
		history.replaceState(null, '', '/panel/node/test');
		const unmatched = window.matchMedia;
		vi.stubGlobal('matchMedia', (media: string) => ({
			...unmatched(media),
			matches: media === '(width >= 88rem)',
		}));
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

		box.querySelector<HTMLElement>('[data-error-jump]')?.click();

		// Opened for the jump, not remembered as the editor's choice.
		expect(inspector.hasAttribute('data-collapsed')).toBe(false);
		expect(document.cookie).not.toContain('inspector=');
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
			if (event.target instanceof Element && event.target.closest('[data-dialog-open="paths"]')) {
				opened++;
			}
		};
		document.addEventListener('click', listener);

		const box = respond([{ path: ['paths', 'en'], message: 'Path is taken' }]);
		box.querySelector<HTMLElement>('[data-error-jump]')?.click();

		document.removeEventListener('click', listener);
		expect(opened).toBe(1);
	});

	it('clears the marks of a field the user edits and drops it from the summary', () => {
		const box = respond([
			{ path: ['content', 'title', 'value', 'en'], message: 'Title is required' },
			{ path: ['content', 'styled', 'value', 'zxx'], message: 'Styled is invalid' },
		]);

		const title = input('content[title][value][en]');
		title.dispatchEvent(new Event('input', { bubbles: true }));

		expect(field('title').hasAttribute('data-invalid')).toBe(false);
		expect(field('title').querySelector('[data-error-message]')).toBeNull();
		expect(title.hasAttribute('aria-invalid')).toBe(false);
		// The untouched field stays, in the summary too.
		expect(field('styled').getAttribute('data-invalid')).toBe('true');
		expect(box.hidden).toBe(false);
		expect(chips(box)).toEqual(['Styled →']);
	});

	it('names each field once however many issues it has', () => {
		const box = respond([
			{ path: ['content', 'title', 'value', 'en'], message: 'Title is too short' },
			{ path: ['content', 'title', 'value', 'en'], message: 'Title has a bad format' },
			{
				path: ['content', 'entries', 'value', 'zxx', 0, 'fields', 'sub', 'value', 'zxx'],
				message: 'Sub is required',
			},
		]);

		expect(chips(box)).toEqual(['Title →', 'sub →']);
		expect(box.querySelector('.cms-errors-lead')?.textContent).toBe('editor:errors-invalid-plural');
		expect(
			Array.from(
				field('title').querySelectorAll('[data-error-message]'),
				(note) => note.textContent,
			),
		).toEqual(['Title is too short', 'Title has a bad format']);
	});

	it('asks for a missing value instead of repeating the label', () => {
		const box = respond([
			{
				path: ['content', 'title', 'value', 'en'],
				message: 'Title is required',
				code: 'rule.required',
			},
		]);

		expect(field('title').querySelector('[data-error-message]')?.textContent).toBe(
			'editor:error-fill',
		);
		expect(box.querySelector('.cms-errors-lead')?.textContent).toBe('editor:errors-empty');
		expect(box.querySelector('.cms-errors-title')?.textContent).toBe('Not saved');
	});

	it('counts the open problems in the header and jumps from its menu', () => {
		respond([
			{ path: ['content', 'title', 'value', 'de'], message: 'Titel fehlt', code: 'missing' },
			{ path: ['content', 'styled', 'value', 'zxx'], message: 'Styled is invalid' },
		]);

		const holder = document.getElementById('editor-problems')!;
		const entries = Array.from(
			holder.querySelectorAll<HTMLElement>('.cms-node-problems-entries [data-error-jump]'),
			(entry) => entry.textContent,
		);

		expect(holder.hidden).toBe(false);
		expect(holder.querySelector('.cms-node-problems-count')?.textContent).toBe(
			'editor:problems-plural',
		);
		expect(entries).toEqual(['Title – editor:error-missing→', 'Styled – editor:error-invalid→']);

		holder.querySelector<HTMLElement>('.cms-node-problems-entries [data-error-jump]')?.click();

		expect(document.activeElement).toBe(input('content[title][value][de]'));
	});

	it('says so once every reported field has been edited', () => {
		const box = respond([
			{ path: ['content', 'styled', 'value', 'zxx'], message: 'Styled is invalid' },
		]);

		input('content[styled][value][zxx]').dispatchEvent(new Event('change', { bubbles: true }));

		expect(box.classList.contains('is-resolved')).toBe(true);
		expect(box.querySelector('.cms-errors-lead')?.textContent).toBe('editor:errors-resolved');
		expect(box.querySelector<HTMLElement>('.cms-errors-title')?.hidden).toBe(true);
		expect(document.getElementById('editor-problems')?.hidden).toBe(true);
	});

	it('keeps an empty required field open and reopens it when emptied again', () => {
		const box = respond([
			{ path: ['content', 'title', 'value', 'en'], message: 'Title is required', code: 'missing' },
		]);
		const title = input('content[title][value][en]');

		title.value = ' ';
		title.dispatchEvent(new Event('input', { bubbles: true }));
		expect(field('title').getAttribute('data-invalid')).toBe('true');

		title.value = 'Hello';
		title.dispatchEvent(new Event('input', { bubbles: true }));
		expect(field('title').hasAttribute('data-invalid')).toBe(false);
		expect(box.classList.contains('is-resolved')).toBe(true);

		title.value = '';
		title.dispatchEvent(new Event('input', { bubbles: true }));
		title.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
		expect(field('title').getAttribute('data-invalid')).toBe('true');
		expect(title.getAttribute('aria-invalid')).toBe('true');
		expect(chips(box)).toEqual(['Title →']);
	});
});
