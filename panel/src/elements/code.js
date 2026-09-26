// Defines <cosray-code>: a CodeMirror editor for code fields, with a syntax
// picker when the field offers more than one language. The host assigns
// the element contract (docs/controls.md) before connecting the element.

/** @import { Extension } from '@codemirror/state' */
/** @import { LocaleMap, Meta } from '../types/data' */
/** @import { ControlLocales } from '../lib/control.js' */
/** @import { ResolvedFallback } from '../lib/fallback.js' */

import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { bracketMatching, foldGutter, foldKeymap, indentOnInput } from '@codemirror/language';
import { Compartment, EditorState } from '@codemirror/state';
import {
	EditorView,
	drawSelection,
	highlightActiveLine,
	highlightActiveLineGutter,
	keymap,
	lineNumbers,
	rectangularSelection,
} from '@codemirror/view';
import { ZXX, ensureLocales, ensureNeutral } from '../lib/content.js';
import { editedLocale, fallbackLabel, reportChange } from '../lib/control.js';
import { resolveFallback } from '../lib/fallback.js';
import { __ } from '../lib/locale.js';
import {
	DEFAULT_CODE_SYNTAX,
	loadCodeLanguageExtension,
	normalizeCodeSyntax,
} from './code/languages.js';
import { cosrayCodeTheme } from './code/theme.js';

/**
 * @typedef {object} CodeField
 * @property {string} name
 * @property {boolean} [required]
 * @property {boolean} [immutable]
 * @property {boolean} [translate]
 * @property {string[]} [syntaxes]
 */

export class CosrayCode extends HTMLElement {
	/** @type {LocaleMap<string> | null | undefined} */
	value = {};
	/** @type {Meta | undefined} */
	meta = {};
	/** @type {CodeField} */
	field = { name: 'code' };
	/** @type {ControlLocales | undefined} */
	locales;

	#locale = ZXX;
	#rendered = false;
	/** @type {LocaleMap<string> | null} Edits so far, read from `value` on first render. */
	#map = null;
	/** @type {LocaleMap<string>} */
	#syntax = {};
	/** @type {ResolvedFallback<string> | null} */
	#fallback = null;
	#focused = false;
	// Language loads are async; a newer mount or syntax choice wins.
	#mounts = 0;
	#syntaxLoads = 0;
	#language = new Compartment();
	/** @type {EditorView | null} */
	#view = null;
	/** @type {EditorView | null} */
	#preview = null;
	/** @type {HTMLElement | null} */
	#previewBox = null;
	#wrap = document.createElement('div');
	#editor = document.createElement('div');
	#input = document.createElement('textarea');

	constructor() {
		super();
		this.#wrap.className = 'cms-code-editor-wrap';
		this.#wrap.addEventListener('focusin', () => this.#focus(true));
		this.#wrap.addEventListener('focusout', (event) => {
			if (!(event.relatedTarget instanceof Node) || !this.#wrap.contains(event.relatedTarget)) {
				this.#focus(false);
			}
		});
		this.#editor.className = 'cms-code-editor';
		this.#input.className = 'cms-code-editor-input';
		this.#input.readOnly = true;
		this.#input.tabIndex = -1;
		this.#input.setAttribute('aria-hidden', 'true');
	}

	/** @type {string} */
	get locale() {
		return this.#locale;
	}

	// The content-language behavior switches the editing locale through
	// the host; a translated field then edits another value.
	set locale(locale) {
		const previous = editedLocale(this.field, this.#locale);
		this.#locale = locale;

		if (this.#rendered && editedLocale(this.field, locale) !== previous) {
			void this.#mount();
		}
	}

	connectedCallback() {
		if (!this.#rendered) {
			this.#render();
		}
	}

	// Sorting blocks moves the element; only a removal that outlasts the
	// current task tears the editor down.
	disconnectedCallback() {
		queueMicrotask(() => {
			if (!this.isConnected && this.#rendered) {
				this.#rendered = false;
				this.#mounts++;
				this.#view?.destroy();
				this.#view = null;
				this.#hidePreview();
				this.replaceChildren();
			}
		});
	}

	#render() {
		if (this.#map === null) {
			const locales = this.locales?.all ?? [];
			this.#map = this.field.translate
				? ensureLocales(this.value ?? undefined, '', locales)
				: ensureNeutral(this.value ?? undefined, '');
			this.#syntax = this.#readSyntax();
		}

		this.replaceChildren();
		const options = this.#syntaxOptions();

		if (options.length > 1) {
			this.append(this.#toolbar(options));
		}

		this.#input.name = this.field.name;
		this.#input.required = this.field.required ?? false;
		this.#wrap.replaceChildren(this.#editor, this.#input);
		this.append(this.#wrap);
		this.#rendered = true;
		void this.#mount();
	}

	/** @returns {string[]} */
	#syntaxOptions() {
		const syntaxes = this.field.syntaxes ?? [];

		return syntaxes.length > 0 ? syntaxes : [DEFAULT_CODE_SYNTAX];
	}

	// The syntax is language-neutral meta; a stored choice the field no
	// longer offers falls back to its first option.
	/** @returns {LocaleMap<string>} */
	#readSyntax() {
		const options = this.#syntaxOptions();
		const fallback = options[0] ?? DEFAULT_CODE_SYNTAX;
		/** @type {LocaleMap<string>} */
		const syntax = { ...(this.meta?.syntax ?? {}) };
		const normalized = normalizeCodeSyntax(syntax[ZXX] ?? fallback);
		syntax[ZXX] = options.includes(normalized) ? normalized : fallback;

		return syntax;
	}

	/**
	 * @param {string[]} options
	 * @returns {HTMLElement}
	 */
	#toolbar(options) {
		const id = `${this.field.name}-syntax`;
		const toolbar = document.createElement('div');
		toolbar.className = 'cms-code-control-toolbar';
		const label = document.createElement('label');
		label.className = 'cms-code-control-syntax-label';
		label.htmlFor = id;
		label.textContent = __('code:syntax');
		const select = document.createElement('select');
		select.className = 'cms-select cms-code-control-syntax-select';
		select.id = id;
		select.disabled = this.field.immutable ?? false;

		for (const option of options) {
			select.append(new Option(option, option));
		}

		select.value = this.#syntax[ZXX];
		select.addEventListener('change', () => {
			this.#syntax[ZXX] = select.value;
			this.#notify();
			void this.#applySyntax();
		});
		toolbar.append(label, select);

		return toolbar;
	}

	// Mounts a fresh editor for the edited locale, so undo history never
	// reaches into another language's text.
	async #mount() {
		const mount = ++this.#mounts;
		const map = this.#edits();
		const active = editedLocale(this.field, this.#locale);
		this.#view?.destroy();
		this.#view = null;
		this.#hidePreview();
		map[active] ??= '';
		this.#input.value = map[active];
		this.#fallback =
			this.field.translate && map[active] === ''
				? resolveFallback(map, active, this.locales?.all ?? [])
				: null;
		this.#showPreview();
		const syntax = this.#syntax[ZXX];
		const language = await loadCodeLanguageExtension(syntax);

		if (mount !== this.#mounts) {
			return;
		}

		this.#view = new EditorView({
			state: EditorState.create({
				doc: map[active],
				extensions: this.#extensions(active, language),
			}),
			parent: this.#editor,
		});

		if (syntax !== this.#syntax[ZXX]) {
			void this.#applySyntax();
		}
	}

	/** @returns {LocaleMap<string>} */
	#edits() {
		if (this.#map === null) {
			throw new Error('cosray-code edits before its first render');
		}

		return this.#map;
	}

	/**
	 * @param {string} active
	 * @param {Extension} language
	 * @returns {Extension[]}
	 */
	#extensions(active, language) {
		return [
			lineNumbers(),
			highlightActiveLineGutter(),
			history(),
			drawSelection(),
			EditorState.allowMultipleSelections.of(true),
			indentOnInput(),
			bracketMatching(),
			rectangularSelection(),
			highlightActiveLine(),
			foldGutter(),
			cosrayCodeTheme,
			keymap.of([...defaultKeymap, ...historyKeymap, ...foldKeymap, indentWithTab]),
			this.#language.of(language),
			EditorState.readOnly.of(this.field.immutable ?? false),
			EditorView.updateListener.of((update) => {
				if (!update.docChanged) {
					return;
				}

				const map = this.#edits();
				map[active] = update.state.doc.toString();
				this.#input.value = map[active];
				this.#showPreview();
				this.#notify();
			}),
		];
	}

	async #applySyntax() {
		const load = ++this.#syntaxLoads;
		const language = await loadCodeLanguageExtension(this.#syntax[ZXX]);

		if (load !== this.#syntaxLoads) {
			return;
		}

		this.#view?.dispatch({ effects: this.#language.reconfigure(language) });

		if (this.#previewBox) {
			this.#hidePreview();
			this.#showPreview();
		}
	}

	/** @param {boolean} focused */
	#focus(focused) {
		this.#focused = focused;

		if (focused) {
			this.#hidePreview();
		} else {
			this.#showPreview();
		}
	}

	// The fallback shows behind an empty, unfocused editor as a read-only
	// rendering with its origin as a badge.
	#showPreview() {
		const active = editedLocale(this.field, this.#locale);
		const fallback = this.#fallback;

		if (fallback === null || this.#edits()[active] !== '' || this.#focused) {
			this.#hidePreview();

			return;
		}

		if (this.#previewBox) {
			return;
		}

		const box = document.createElement('div');
		box.className = 'cms-code-editor-fallback';
		box.setAttribute('aria-hidden', 'true');
		const code = document.createElement('div');
		code.className = 'cms-code-editor cms-code-editor-preview';
		const badge = document.createElement('span');
		badge.textContent = fallbackLabel(fallback, this.locales?.all ?? []);
		box.append(code, badge);
		this.#wrap.prepend(box);
		this.#previewBox = box;

		void loadCodeLanguageExtension(this.#syntax[ZXX]).then((language) => {
			if (this.#previewBox !== box) {
				return;
			}

			this.#preview = new EditorView({
				state: EditorState.create({
					doc: fallback.value,
					extensions: [
						lineNumbers(),
						cosrayCodeTheme,
						language,
						EditorState.readOnly.of(true),
						EditorView.editable.of(false),
					],
				}),
				parent: code,
			});
		});
	}

	#hidePreview() {
		this.#preview?.destroy();
		this.#preview = null;
		this.#previewBox?.remove();
		this.#previewBox = null;
	}

	#notify() {
		reportChange(this, {
			value: this.#edits(),
			meta: { ...(this.meta ?? {}), syntax: this.#syntax },
		});
	}
}

if (!customElements.get('cosray-code')) {
	customElements.define('cosray-code', CosrayCode);
}
