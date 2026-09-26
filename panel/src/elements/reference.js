// Defines <cosray-reference>: the node reference picker. A combobox queries
// the panel's reference endpoint and picked nodes stack as removable rows;
// a field limited to one node edits it in the box itself. The stored value
// is language-neutral: a zxx list of uids.

/** @import { LocaleMap } from '../types/data' */

import { ZXX } from '../lib/content.js';
import { reportChange } from '../lib/control.js';
import { icon } from '../lib/icons.js';
import { __ } from '../lib/locale.js';
import { panelBase } from '../lib/runtime.js';

/**
 * @typedef {object} NodeInfo
 * @property {string} uid
 * @property {string} title
 * @property {string} type
 * @property {string} typeLabel
 */

/**
 * @typedef {object} ReferenceField
 * @property {string} [name]
 * @property {string} [label]
 * @property {boolean} [immutable]
 * @property {string} [ownerType]
 * @property {{ max?: number }} [limit]
 */

let ids = 0;

/**
 * @param {string} tag
 * @param {string} [className]
 * @returns {HTMLElement}
 */
function create(tag, className) {
	const element = document.createElement(tag);

	if (className) {
		element.className = className;
	}

	return element;
}

/**
 * Keeps an element in its parent while shown, right after `after` or
 * first, and out of it otherwise. Only a change touches the DOM, so a
 * focused element stays focused.
 *
 * @param {Element} parent
 * @param {Element} element
 * @param {boolean} shown
 * @param {Element | null} [after]
 */
function place(parent, element, shown, after = null) {
	if (!shown) {
		element.remove();
	} else if (element.parentNode !== parent) {
		if (after && after.parentNode === parent) {
			after.after(element);
		} else {
			parent.prepend(element);
		}
	}
}

/**
 * @param {string} path
 * @param {URLSearchParams} params
 * @param {AbortSignal} signal
 * @returns {Promise<{ nodes: NodeInfo[], more: boolean }>}
 */
async function query(path, params, signal) {
	const response = await fetch(`${panelBase()}${path}?${params.toString()}`, {
		credentials: 'same-origin',
		headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
		signal,
	});

	if (!response.ok) {
		throw new Error('Could not load reference entries.');
	}

	const data = await response.json();

	if (!data.ok) {
		throw new Error('Could not load reference entries.');
	}

	return data;
}

export class CosrayReference extends HTMLElement {
	/** @type {LocaleMap<{ uid: string }[]> | null | undefined} */
	value = {};
	/** @type {ReferenceField} */
	field = { name: 'reference' };
	node = '';

	#id = `cms-reference-${++ids}`;
	#started = false;
	/** @type {NodeInfo[]} */
	#items = [];
	#resolving = false;
	#q = '';
	/** @type {NodeInfo[]} */
	#results = [];
	#open = false;
	#loading = false;
	#failed = false;
	#more = false;
	#active = -1;
	#offset = 0;
	/** @type {ReturnType<typeof setTimeout> | undefined} */
	#timer;
	/** @type {AbortController | undefined} */
	#request;
	/** @type {AbortController | undefined} */
	#labels;
	#events = new AbortController();

	#root = create('div', 'cms-reference');
	#search = create('div', 'cms-reference-search');
	#input = document.createElement('input');
	#wrap = create('div', 'control-wrap');
	#tools = create('div', 'single-tools');
	#clear = document.createElement('button');
	#toggleButton = document.createElement('button');
	#popup = create('div', 'cms-reference-popup');
	#recent = create('div', 'cms-reference-note');
	#list = create('ul', 'cms-reference-results');
	#status = create('div', 'cms-reference-note');
	#page = document.createElement('button');
	#refine = create('div', 'cms-reference-note');
	#selected = create('ul', 'cms-reference-list');

	get #immutable() {
		return this.field?.immutable === true;
	}

	get #ownerType() {
		return typeof this.field?.ownerType === 'string' ? this.field.ownerType : '';
	}

	get #fieldName() {
		return typeof this.field?.name === 'string' ? this.field.name : '';
	}

	get #max() {
		return typeof this.field?.limit?.max === 'number' ? this.field.limit.max : -1;
	}

	get #single() {
		return this.#max === 1;
	}

	get #label() {
		return this.field?.label || this.#fieldName || __('node:search');
	}

	get #title() {
		const first = this.#items[0];

		return first ? first.title || (this.#resolving ? __('common:loading') : first.uid) : '';
	}

	get #choices() {
		return this.#single ? this.#results : this.#results.filter((result) => !this.#has(result.uid));
	}

	get #activeId() {
		return this.#open && this.#active >= 0 && this.#choices[this.#active]
			? `${this.#id}-${this.#active}`
			: undefined;
	}

	connectedCallback() {
		if (this.#started) {
			return;
		}

		this.#started = true;
		this.#build();
		this.#resolve();
		this.#update();
	}

	disconnectedCallback() {
		queueMicrotask(() => {
			if (!this.isConnected) {
				this.#cancel();
				this.#labels?.abort();
			}
		});
	}

	#build() {
		const events = { signal: this.#events.signal };
		this.#input.className = 'cms-input';
		this.#input.type = 'text';
		this.#input.autocomplete = 'off';
		this.#input.addEventListener('input', () => {
			this.#q = this.#input.value;
			this.#searchNow();
		});
		this.#input.addEventListener('focus', () => this.#show());
		this.#input.addEventListener('click', () => this.#show());
		this.#wrap.append(this.#input);
		this.#clear.type = 'button';
		this.#clear.setAttribute('aria-label', __('common:remove'));
		this.#clear.innerHTML = icon('x-lg');
		this.#clear.addEventListener('click', () => {
			if (this.#items[0]) this.#remove(this.#items[0].uid);
		});
		this.#toggleButton.type = 'button';
		this.#toggleButton.tabIndex = -1;
		this.#toggleButton.setAttribute('aria-controls', `${this.#id}-results`);
		this.#toggleButton.innerHTML = icon('chevron-down');
		this.#toggleButton.addEventListener('click', () => this.#toggle());
		this.#tools.append(this.#toggleButton);
		this.#list.id = `${this.#id}-results`;
		this.#list.setAttribute('role', 'listbox');
		this.#status.setAttribute('role', 'status');
		this.#page.type = 'button';
		this.#page.className = 'cms-reference-result';
		this.#page.addEventListener('click', () => {
			if (!this.#loading) {
				void this.#load();
			}
		});
		this.#recent.textContent = __('reference:recent');
		this.#refine.textContent = __('reference:refine');
		this.#popup.append(this.#list, this.#status);
		this.#search.append(this.#wrap, this.#popup);
		this.#search.addEventListener('focusout', (event) => {
			if (!(event.relatedTarget instanceof Node) || !this.#search.contains(event.relatedTarget)) {
				this.#close();
			}
		});
		// Every control of the box shares the keyboard handling.
		this.#search.addEventListener('keydown', (event) => void this.#keydown(event));
		document.addEventListener(
			'pointerdown',
			(event) => {
				if (this.#open && !(event.target instanceof Node && this.contains(event.target))) {
					this.#close();
				}
			},
			events,
		);
		this.replaceChildren(this.#root);
	}

	// Stored uids show at once; their titles follow from the labels endpoint.
	#resolve() {
		const uids = (this.value?.[ZXX] ?? [])
			.map((item) => (item && typeof item.uid === 'string' ? item.uid : ''))
			.filter((uid) => uid !== '');

		if (uids.length === 0) {
			return;
		}

		this.#resolving = true;
		this.#items = uids.map((uid) => ({ uid, title: '', type: '', typeLabel: '' }));
		const controller = new AbortController();
		this.#labels = controller;

		query('reference/labels', new URLSearchParams({ uids: uids.join(',') }), controller.signal)
			.then(({ nodes }) => {
				if (controller.signal.aborted) return;

				const map = new Map(nodes.map((node) => [node.uid, node]));
				this.#items = this.#items.map((item) => map.get(item.uid) ?? item);
			})
			.catch(() => {})
			.finally(() => {
				if (!controller.signal.aborted) {
					this.#resolving = false;
					this.#update();
				}
			});
	}

	#full() {
		return this.#max >= 1 && this.#items.length >= this.#max;
	}

	/** @param {string} uid */
	#has(uid) {
		return this.#items.some((item) => item.uid === uid);
	}

	#emit() {
		reportChange(this, { value: { [ZXX]: this.#items.map((item) => ({ uid: item.uid })) } });
	}

	/** @param {NodeInfo} info */
	#choose(info) {
		if (this.#immutable) return;

		if (this.#single) {
			if (!this.#has(info.uid)) {
				this.#items = [info];
				this.#emit();
			}

			this.#input.focus();
			this.#close();

			return;
		}

		if (this.#has(info.uid) || this.#full()) {
			return;
		}

		this.#items = [...this.#items, info];
		this.#active = -1;
		this.#emit();

		if (this.#full()) {
			this.#q = '';
			this.#close();
		} else if (this.#q !== '') {
			this.#q = '';
			this.#searchNow();
		}

		this.#update();

		if (this.#full()) {
			this.#selected.lastElementChild?.querySelector('button')?.focus();
		} else {
			this.#input.focus();
		}
	}

	/** @param {string} uid */
	#remove(uid) {
		if (this.#immutable) {
			return;
		}

		this.#items = this.#items.filter((item) => item.uid !== uid);
		this.#active = -1;
		this.#emit();

		if (this.#single) {
			this.#q = '';
			this.#searchNow();
		}

		this.#update();
		this.#input.focus();
	}

	#cancel() {
		clearTimeout(this.#timer);
		this.#request?.abort();
		this.#loading = false;
	}

	#close() {
		this.#cancel();
		this.#open = false;
		this.#active = -1;

		if (this.#single) this.#q = '';

		this.#update();
	}

	async #load() {
		const controller = new AbortController();
		this.#request = controller;
		this.#loading = true;
		this.#update();
		const params = new URLSearchParams({
			type: this.#ownerType,
			field: this.#fieldName,
			q: this.#q.trim(),
			offset: String(this.#offset),
			limit: '30',
		});

		if (this.node !== '') {
			params.set('node', this.node);
		}

		try {
			const data = await query('reference/search', params, controller.signal);

			if (controller.signal.aborted) return;

			// Paging counts server rows, including entries already selected in this field.
			this.#offset += data.nodes.length;
			this.#results = [
				...this.#results,
				...data.nodes.filter((node) => !this.#results.some((result) => result.uid === node.uid)),
			];
			this.#more = data.more;
			this.#failed = false;

			if (!this.#more && document.activeElement === this.#page) {
				this.#input.focus();
			}
		} catch {
			if (controller.signal.aborted) return;

			this.#failed = true;
		} finally {
			if (!controller.signal.aborted) {
				this.#loading = false;
				this.#update();
			}
		}
	}

	#searchNow() {
		this.#cancel();

		if (this.#immutable || (!this.#single && this.#full()) || this.#ownerType === '') {
			this.#update();
			return;
		}

		this.#results = [];
		this.#offset = 0;
		this.#more = false;
		this.#failed = false;
		this.#active = -1;
		this.#open = true;
		this.#loading = true;

		if (this.#q.trim() === '') {
			void this.#load();
		} else {
			this.#timer = setTimeout(() => void this.#load(), 200);
			this.#update();
		}
	}

	#show() {
		if (!this.#open) this.#searchNow();
	}

	#toggle() {
		const wasOpen = this.#open;
		this.#input.focus();

		if (wasOpen) this.#close();
		else this.#show();
	}

	/** @param {KeyboardEvent} event */
	async #keydown(event) {
		if (this.#immutable || event.isComposing) return;

		if (event.key === 'Escape' && this.#open) {
			event.preventDefault();
			event.stopPropagation();
			this.#input.focus();
			this.#close();

			return;
		}

		if (event.target !== this.#input) return;

		const choices = this.#choices;

		if (event.key === 'Enter') {
			event.preventDefault();
			event.stopPropagation();

			if (!this.#open && this.#single) this.#show();
			else if (this.#open && choices[this.#active]) this.#choose(choices[this.#active]);

			return;
		}

		if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;

		event.preventDefault();
		event.stopPropagation();
		this.#show();
		const count = this.#choices.length;

		if (count === 0) return;

		this.#active =
			event.key === 'ArrowDown'
				? Math.min(this.#active + 1, count - 1)
				: this.#active < 0
					? count - 1
					: Math.max(this.#active - 1, 0);
		this.#update();
		document.getElementById(this.#activeId ?? '')?.scrollIntoView({ block: 'nearest' });
	}

	// Brings the markup in line with the state. The input, the popup's parts
	// and the paging button persist, so focus and caret survive; the option
	// and selection rows are rebuilt.
	#update() {
		const single = this.#single;
		const immutable = this.#immutable;
		const full = this.#full();
		const open = this.#open;
		const q = this.#q;
		const choices = this.#choices;
		const label = this.#label;
		const title = this.#title;

		place(this.#root, this.#search, single || (!full && !immutable));

		const input = this.#input;
		input.classList.toggle('single-input', single);
		input.classList.toggle('has-selection', single && this.#items.length > 0);
		input.setAttribute('aria-label', label);
		input.readOnly = immutable;
		input.placeholder = single && title ? title : __('reference:placeholder');

		if (immutable) {
			for (const name of ['role', 'aria-autocomplete', 'aria-expanded', 'aria-controls']) {
				input.removeAttribute(name);
			}
		} else {
			input.setAttribute('role', 'combobox');
			input.setAttribute('aria-autocomplete', 'list');
			input.setAttribute('aria-expanded', String(open));
			input.setAttribute('aria-controls', `${this.#id}-results`);
		}

		const activeId = this.#activeId;

		if (activeId) {
			input.setAttribute('aria-activedescendant', activeId);
		} else {
			input.removeAttribute('aria-activedescendant');
		}

		const shown = single && !open ? title : q;

		if (input.value !== shown) {
			input.value = shown;
		}

		place(this.#wrap, this.#tools, single && !immutable, input);
		place(this.#tools, this.#clear, this.#items.length > 0);
		this.#toggleButton.setAttribute('aria-label', open ? __('common:close') : __('common:open'));
		this.#toggleButton.setAttribute('aria-expanded', String(open));
		this.#popup.hidden = !open;
		place(this.#popup, this.#recent, q.trim() === '');
		this.#list.setAttribute('aria-label', label);
		this.#list.setAttribute('aria-busy', String(this.#loading));
		this.#list.replaceChildren(...choices.map((result, index) => this.#option(result, index)));
		this.#status.textContent = open ? this.#statusText(choices) : '';
		place(this.#popup, this.#page, this.#more || this.#failed, this.#status);
		this.#page.setAttribute('aria-disabled', String(this.#loading));
		this.#page.textContent = this.#failed ? __('common:retry') : __('common:load-more');
		place(this.#popup, this.#refine, this.#more, this.#page);
		this.#renderSelected(!single && this.#items.length > 0, immutable);
	}

	/** @param {NodeInfo[]} choices */
	#statusText(choices) {
		if (this.#loading) return __('common:loading');
		if (this.#failed) return __('reference:failed');
		if (choices.length > 0) return __('reference:result-count', { count: choices.length });
		if (this.#more) return __('reference:loaded-selected');
		if (this.#q.trim() !== '' && this.#results.length === 0) return __('search:no-results');

		return __('reference:empty');
	}

	/**
	 * @param {NodeInfo} result
	 * @param {number} index
	 */
	#option(result, index) {
		const item = create('li');
		item.setAttribute('role', 'presentation');
		const button = document.createElement('button');
		button.id = `${this.#id}-${index}`;
		button.type = 'button';
		button.setAttribute('role', 'option');
		button.tabIndex = -1;
		button.setAttribute(
			'aria-selected',
			String(this.#single ? this.#has(result.uid) : index === this.#active),
		);
		button.className = 'cms-reference-result';
		button.classList.toggle('is-active', index === this.#active);
		// The input keeps focus while the pointer picks.
		button.addEventListener('mousedown', (event) => event.preventDefault());
		button.addEventListener('click', () => this.#choose(result));
		const title = create('span', 'cms-reference-title');
		title.textContent = result.title || result.uid;
		button.append(title);

		// The space keeps title and type apart in the option's accessible name.
		if (result.typeLabel) {
			const type = create('span', 'cms-reference-type');
			type.textContent = result.typeLabel;
			button.append(' ', type);
		}

		if (this.#single && this.#has(result.uid)) {
			button.insertAdjacentHTML('beforeend', icon('check-lg'));
		}

		item.append(button);

		return item;
	}

	/**
	 * @param {boolean} shown
	 * @param {boolean} immutable
	 */
	#renderSelected(shown, immutable) {
		place(this.#root, this.#selected, shown, this.#search);

		if (!shown) {
			return;
		}

		this.#selected.replaceChildren(
			...this.#items.map((item) => {
				const row = create('li', 'cms-reference-item');
				const title = create('span', 'cms-reference-title');
				title.textContent = item.title || (this.#resolving ? __('common:loading') : item.uid);
				row.append(title);

				if (item.typeLabel) {
					const type = create('span', 'cms-reference-type');
					type.textContent = item.typeLabel;
					row.append(' ', type);
				}

				if (!immutable) {
					const remove = document.createElement('button');
					remove.type = 'button';
					remove.className = 'cms-reference-remove';
					remove.setAttribute('aria-label', __('common:remove'));
					remove.innerHTML = icon('x-lg');
					remove.addEventListener('click', () => this.#remove(item.uid));
					row.append(remove);
				}

				return row;
			}),
		);
	}
}

if (!customElements.get('cosray-reference')) {
	customElements.define('cosray-reference', CosrayReference);
}
