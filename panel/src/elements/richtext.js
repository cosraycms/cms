// Defines <cosray-richtext>: the ProseMirror editor for rich text fields.
// A field shown as a block gets a bubble over the selection, every other a
// toolbar. The host assigns the element contract (docs/controls.md); edits
// report the whole value map in the cosray richtext envelope.

/** @import { EditorState } from 'prosemirror-state' */
/** @import { AssetInfo, AssetMap, LocaleMap } from '../types/data' */
/** @import { ControlLocales } from '../lib/control.js' */
/** @import { CmsEditor } from './richtext/editor.js' */
/** @import { RichtextDoc, RichtextValue } from './richtext/format.js' */

import { DOMSerializer } from 'prosemirror-model';
import { redo, undo } from 'prosemirror-history';
import { ZXX } from '../lib/content.js';
import { editedLocale, fallbackLabel, reportChange } from '../lib/control.js';
import { resolveFallback } from '../lib/fallback.js';
import { icon } from '../lib/icons.js';
import { __ } from '../lib/locale.js';
import {
	clearMarks,
	clearNodes,
	insertHardBreak,
	insertHorizontalRule,
	insertImage,
	setHeading,
	setLink,
	setParagraph,
	setParagraphClass,
	setStyle,
	setTextAlign,
	toggleBlockquote,
	toggleBold,
	toggleBulletList,
	toggleItalic,
	toggleOrderedList,
	toggleStrike,
	toggleSubscript,
	toggleSuperscript,
	unsetLink,
	unsetStyle,
	unsetTextAlign,
} from './richtext/commands.js';
import { openImageDialog, openLinkDialog } from './richtext/dialogs.js';
import createEditor from './richtext/editor.js';
import { FORMAT, VERSION, docToPm, htmlToDoc, isFilledDoc } from './richtext/format.js';
import { schema } from './richtext/schema.js';
import {
	getActiveTextAlign,
	getBlockAttributes,
	getMarkAttributes,
	isMarkActive,
	isNodeActive,
} from './richtext/state-helpers.js';

/**
 * @typedef {object} RichtextField
 * @property {string} name
 * @property {boolean} [required]
 * @property {boolean} [immutable]
 * @property {boolean} [translate]
 * @property {string} [presentation]
 * @property {string} [placeholder]
 * @property {string[]} [tools]
 * @property {Record<string, string>} [richtextClasses]
 * @property {Record<string, string>} [richtextStyles]
 */

/**
 * @typedef {object} ToolSpec
 * @property {string} key
 * @property {string} tool
 * @property {string} icon
 * @property {string} label
 * @property {() => void} run
 * @property {() => boolean} [active]
 * @property {() => boolean} [visible]
 */

// Mirrors Cosray\Schema\Tool::defaults(): the set an editor gets when
// neither the field nor the project configures one.
const DEFAULT_TOOLS = [
	'undo',
	'redo',
	'bold',
	'italic',
	'strike',
	'h2',
	'h3',
	'bullet-list',
	'ordered-list',
	'link',
];

// The bubble shows on a selection: tools that act on the document as a
// whole have no place in it, block styles fold into one menu.
const BUBBLELESS = new Set(['undo', 'redo', 'source', 'hr', 'br', 'image', 'align']);
const BLOCK_STYLES = new Set(['h1', 'h2', 'h3', 'blockquote']);

let ids = 0;

/**
 * @param {string} tag
 * @param {string} [className]
 * @returns {HTMLElement}
 */
function create(tag, className) {
	const element = document.createElement(tag);

	if (className) element.className = className;

	return element;
}

/**
 * @param {string} name
 * @returns {Element}
 */
function svg(name) {
	const template = document.createElement('template');
	template.innerHTML = icon(name);

	return /** @type {Element} */ (template.content.firstElementChild);
}

// A click in the bubble must not take the focus, or the selection it acts
// on collapses before the command runs.
/** @param {MouseEvent} event */
function keepFocus(event) {
	event.preventDefault();
}

/**
 * A tool button whose active state and presence follow the editor state.
 *
 * @param {(() => void)[]} updates
 * @param {HTMLElement} parent
 * @param {ToolSpec} spec
 * @param {'button' | 'bubble' | 'menu'} kind
 */
function tool(updates, parent, spec, kind) {
	const control = document.createElement('button');
	control.type = 'button';
	control.append(svg(spec.icon));

	if (kind === 'menu') {
		control.setAttribute('role', spec.active ? 'menuitemcheckbox' : 'menuitem');
		const text = create('span');
		text.textContent = spec.label;
		control.append(' ', text);
	} else {
		control.className = 'richtext-toolbar-btn';
		control.title = spec.label;
		control.setAttribute('aria-label', spec.label);
	}

	if (kind === 'bubble') {
		control.addEventListener('mousedown', keepFocus);
	}

	control.addEventListener('click', spec.run);
	// The anchor keeps the place of a tool that is hidden for now.
	const anchor = document.createComment(spec.key);
	parent.append(control, anchor);
	updates.push(() => {
		const active = spec.active?.() ?? false;

		if (kind === 'menu') {
			control.classList.toggle('is-active', active);

			if (spec.active) control.setAttribute('aria-checked', String(active));
		} else {
			control.classList.toggle('active', active);
		}

		const visible = spec.visible?.() ?? true;

		if (visible && !control.isConnected) anchor.before(control);
		else if (!visible) control.remove();
	});
}

/**
 * An entry of an action menu; with `checked` a radio that follows the
 * editor state.
 *
 * @param {(() => void)[]} updates
 * @param {HTMLElement} menu
 * @param {object} entry
 * @param {string | null} entry.icon
 * @param {string} entry.label
 * @param {() => void} entry.run
 * @param {() => boolean} [entry.checked]
 * @param {boolean} [entry.bubbled] Keeps the editor focused, as in the bubble.
 */
function entry(updates, menu, { icon: name, label, run, checked, bubbled = false }) {
	const control = document.createElement('button');
	control.type = 'button';

	if (name) {
		const text = create('span');
		text.textContent = label;
		control.append(svg(name), ' ', text);
	} else {
		control.append(label);
	}

	control.addEventListener('click', run);

	if (bubbled) control.addEventListener('mousedown', keepFocus);

	if (checked) {
		control.setAttribute('role', 'menuitemradio');
		updates.push(() => {
			control.setAttribute('aria-checked', String(checked()));
			control.classList.toggle('is-active', checked());
		});
	}

	menu.append(control);
}

/**
 * A menu trigger with its popover action menu.
 *
 * @param {string} id
 * @param {Node[]} face
 * @param {Record<string, string>} attributes
 * @returns {{ wrap: HTMLElement, trigger: HTMLButtonElement, menu: HTMLElement }}
 */
function dropdown(id, face, attributes) {
	const wrap = create('div', 'cms-richtext-dropdown-wrap');
	const trigger = document.createElement('button');
	trigger.type = 'button';
	trigger.setAttribute('popovertarget', id);
	trigger.setAttribute('aria-haspopup', 'menu');

	for (const [name, value] of Object.entries(attributes)) {
		trigger.setAttribute(name, value);
	}

	trigger.append(...face);
	const menu = create('div', 'cms-action-menu');
	menu.id = id;
	menu.setAttribute('popover', 'auto');
	menu.dataset.actionMenu = '';
	wrap.append(trigger, menu);

	return { wrap, trigger, menu };
}

export class CosrayRichtext extends HTMLElement {
	/** @type {LocaleMap<RichtextDoc | string | null> | null | undefined} */
	value = {};
	format = '';
	/** @type {RichtextField} */
	field = { name: 'richtext' };
	/** @type {ControlLocales | undefined} */
	locales;
	/** @type {AssetMap} */
	assets = {};

	#locale = ZXX;
	#rendered = false;
	/** @type {RichtextValue | null} */
	#map = null;
	// Library picks and uploads register here so previews resolve before
	// the payload knows the asset.
	/** @type {AssetMap} */
	#picked = {};
	/** @type {CmsEditor | null} */
	#editor = null;
	/** @type {(() => void) | null} */
	#refresh = null;

	/** @type {string} */
	get locale() {
		return this.#locale;
	}

	// The content-language behavior switches the editing locale through the
	// host; a translated field then edits another document.
	set locale(locale) {
		const previous = editedLocale(this.field, this.#locale);
		this.#locale = locale;

		if (this.#rendered && editedLocale(this.field, locale) !== previous) {
			this.#mount();
		}
	}

	connectedCallback() {
		if (this.#rendered) {
			return;
		}

		const fresh = this.#map === null;
		this.#rendered = true;
		this.#mount();

		// Legacy content converts once, so an untouched field still submits
		// the structured envelope (saves are writer-strict).
		if (fresh && this.format !== FORMAT) {
			this.#notify();
		}
	}

	// Sorting blocks moves the element; only a removal that outlasts the
	// current task tears the editor down.
	disconnectedCallback() {
		queueMicrotask(() => {
			if (!this.isConnected && this.#rendered) {
				this.#rendered = false;
				this.#editor?.destroy();
				this.#editor = null;
				this.replaceChildren();
			}
		});
	}

	/** @returns {RichtextValue} */
	#values() {
		if (this.#map === null) {
			this.#map = this.#convert();
		}

		return this.#map;
	}

	/**
	 * All locales convert at once: legacy HTML parses through the editor
	 * schema, structured documents pass through. The result is always a
	 * complete map.
	 *
	 * @returns {RichtextValue}
	 */
	#convert() {
		/** @type {RichtextValue} */
		const map = {};
		// A control the host renders without stored data, such as a freshly
		// stamped row, assigns a null value rather than an absent one.
		const source = this.value ?? {};
		const ids = this.field.translate ? (this.locales?.all ?? []).map((entry) => entry.id) : [ZXX];

		for (const id of new Set([...Object.keys(source), ...ids])) {
			const raw = source[id] ?? null;
			map[id] = typeof raw === 'string' ? htmlToDoc(raw) : raw;
		}

		return map;
	}

	#notify() {
		reportChange(this, { value: this.#values(), format: FORMAT, version: VERSION });
	}

	/**
	 * @param {string} uid
	 * @returns {string | null}
	 */
	#assetUrl(uid) {
		const info = this.#picked[uid] ?? this.assets[uid];

		return info?.thumbUrl ?? info?.url ?? null;
	}

	// Builds the editor for the edited locale; a fresh one per locale keeps
	// undo history inside one language.
	#mount() {
		this.#editor?.destroy();
		this.#editor = null;

		const map = this.#values();
		const active = editedLocale(this.field, this.#locale);
		const locales = this.locales?.all ?? [];
		const fallback =
			this.field.translate && !isFilledDoc(map[active])
				? resolveFallback(map, active, locales, (doc) => isFilledDoc(doc))
				: null;

		this.replaceChildren(
			this.#build(
				active,
				fallback?.value ?? null,
				fallback ? fallbackLabel(fallback, locales) : '',
			),
		);
	}

	/**
	 * @param {string} active
	 * @param {RichtextDoc | null} fallback
	 * @param {string} fallbackText
	 * @returns {HTMLElement}
	 */
	#build(active, fallback, fallbackText) {
		const field = this.field;
		const readonly = field.immutable ?? false;
		const mode = field.presentation === 'block' ? 'inline' : 'default';
		const tools = new Set(field.tools ?? DEFAULT_TOOLS);
		const menuId = `cms-richtext-${++ids}`;
		const map = this.#values();
		const root = create('div', `richtext richtext-${mode}`);
		root.classList.toggle('required', field.required ?? false);
		const stack = create('div', 'cms-richtext-stack');
		const content = create('div', 'richtext-editor cms-richtext-content cms-richtext-layer-base');
		content.dataset.name = field.name;
		const sourceBox = create(
			'div',
			'richtext-source cms-richtext-source cms-richtext-layer-base hide',
		);
		// No name: the host carries the value into the form. A named textarea
		// would submit a bare key that, for a sub-field called "content"
		// inside entries, wipes the whole content tree.
		const source = document.createElement('textarea');
		source.className = 'cms-richtext-source-input';
		source.readOnly = readonly;
		sourceBox.append(source);
		/** @type {HTMLElement | null} */
		let fallbackBox = null;
		let focused = false;
		let showSource = false;
		const state = {
			bold: false,
			heading1: false,
			heading2: false,
			heading3: false,
			/** @type {string | null} */
			paragraphClass: null,
			center: false,
			right: false,
			justify: false,
			italic: false,
			strike: false,
			bulletList: false,
			orderedList: false,
			subscript: false,
			superscript: false,
			blockquote: false,
			link: false,
			/** @type {string | null} */
			styleClass: null,
		};

		if (isFilledDoc(fallback)) {
			fallbackBox = create('div', 'cms-richtext-fallback');
			fallbackBox.setAttribute('aria-hidden', 'true');
			const preview = create('div', 'ProseMirror');
			preview.append(
				DOMSerializer.fromSchema(schema).serializeFragment(docToPm(fallback).content, {
					document,
				}),
			);

			for (const image of preview.querySelectorAll('img[data-uid]')) {
				const url = this.#assetUrl(image.getAttribute('data-uid') ?? '');

				if (url && image instanceof HTMLImageElement) image.src = url;
			}

			const badge = create('span');
			badge.textContent = fallbackText;
			fallbackBox.append(preview, badge);
		}

		const showFallback = () => {
			const shown = fallbackBox !== null && !isFilledDoc(map[active]) && !focused && !showSource;
			stack.classList.toggle('has-fallback', shown);

			if (fallbackBox && shown !== fallbackBox.isConnected) {
				if (shown) stack.prepend(fallbackBox);
				else fallbackBox.remove();
			}
		};

		root.addEventListener('focusin', () => {
			focused = true;
			showFallback();
		});
		root.addEventListener('focusout', (event) => {
			if (!(event.relatedTarget instanceof Node) || !root.contains(event.relatedTarget)) {
				focused = false;
				showFallback();
			}
		});
		stack.append(content, sourceBox);
		/** @type {HTMLElement | undefined} */
		let bubble;

		if (mode === 'inline' && !readonly) {
			bubble = create('div', 'richtext-bubble cms-richtext-bubble');
			root.append(bubble);
		}

		root.append(stack);
		showFallback();

		/** @param {(state: EditorState, dispatch?: any, view?: any) => boolean} command */
		const run = (command) => () => this.#editor?.run(command);

		/** @param {1 | 2 | 3} level */
		const toggleHeading = (level) => () => {
			const active = level === 1 ? state.heading1 : level === 2 ? state.heading2 : state.heading3;
			run(active ? setParagraph() : setHeading(level))();
		};

		const link = () => {
			const editor = this.#editor;

			if (!editor) return;

			const attrs = getMarkAttributes(editor.view.state, schema.marks.link);
			openLinkDialog({
				owner: content,
				href: typeof attrs?.href === 'string' ? attrs.href : '',
				node: typeof attrs?.node === 'string' ? attrs.node : '',
				asset: typeof attrs?.asset === 'string' ? attrs.asset : '',
				blank: (attrs?.target ?? '') === '_blank',
				add: (target, blank) => {
					const href = target.href ?? '';
					const node = target.node ?? '';
					const asset = target.asset ?? '';

					if (href === '' && node === '' && asset === '') return;

					this.#editor?.run(
						setLink({
							href: href || null,
							node: node || null,
							asset: asset || null,
							target: blank ? '_blank' : '',
							class: undefined,
						}),
					);
				},
			});
		};

		const image = () => {
			if (!this.#editor) return;

			openImageDialog({
				owner: content,
				/**
				 * @param {string} uid
				 * @param {AssetInfo} info
				 */
				add: (uid, info) => {
					this.#picked = { ...this.#picked, [uid]: info };
					this.#editor?.run(insertImage(uid));
				},
			});
		};

		// The full vocabulary in canonical order; `tools` picks the subset, so
		// a configured list is a set, not a layout.
		/** @type {ToolSpec[]} */
		const specs = [
			{
				key: 'undo',
				tool: 'undo',
				icon: 'arrow-counterclockwise',
				label: __('richtext:undo'),
				run: run(undo),
			},
			{
				key: 'redo',
				tool: 'redo',
				icon: 'arrow-clockwise',
				label: __('richtext:redo'),
				run: run(redo),
			},
			{
				key: 'bold',
				tool: 'bold',
				icon: 'type-bold',
				label: __('richtext:bold'),
				run: run(toggleBold()),
				active: () => state.bold,
			},
			{
				key: 'italic',
				tool: 'italic',
				icon: 'type-italic',
				label: __('richtext:italic'),
				run: run(toggleItalic()),
				active: () => state.italic,
			},
			{
				key: 'strike',
				tool: 'strike',
				icon: 'type-strikethrough',
				label: __('richtext:strikethrough'),
				run: run(toggleStrike()),
				active: () => state.strike,
			},
			{
				key: 'h1',
				tool: 'h1',
				icon: 'type-h1',
				label: __('richtext:heading-1'),
				run: toggleHeading(1),
				active: () => state.heading1,
			},
			{
				key: 'h2',
				tool: 'h2',
				icon: 'type-h2',
				label: __('richtext:heading-2'),
				run: toggleHeading(2),
				active: () => state.heading2,
			},
			{
				key: 'h3',
				tool: 'h3',
				icon: 'type-h3',
				label: __('richtext:heading-3'),
				run: toggleHeading(3),
				active: () => state.heading3,
			},
			{
				key: 'sub',
				tool: 'sub',
				icon: 'subscript',
				label: __('richtext:subscript'),
				run: run(toggleSubscript()),
				active: () => state.subscript,
			},
			{
				key: 'sup',
				tool: 'sup',
				icon: 'superscript',
				label: __('richtext:superscript'),
				run: run(toggleSuperscript()),
				active: () => state.superscript,
			},
			{
				key: 'align-left',
				tool: 'align',
				icon: 'text-left',
				label: __('richtext:align-left'),
				run: run(unsetTextAlign()),
			},
			{
				key: 'align-center',
				tool: 'align',
				icon: 'text-center',
				label: __('richtext:align-center'),
				run: run(setTextAlign('center')),
				active: () => state.center,
			},
			{
				key: 'align-right',
				tool: 'align',
				icon: 'text-right',
				label: __('richtext:align-right'),
				run: run(setTextAlign('right')),
				active: () => state.right,
			},
			{
				key: 'align-justify',
				tool: 'align',
				icon: 'justify',
				label: __('richtext:justify'),
				run: run(setTextAlign('justify')),
				active: () => state.justify,
			},
			{
				key: 'bullet-list',
				tool: 'bullet-list',
				icon: 'list-ul',
				label: __('richtext:bullet-list'),
				run: run(toggleBulletList()),
				active: () => state.bulletList,
			},
			{
				key: 'ordered-list',
				tool: 'ordered-list',
				icon: 'list-ol',
				label: __('richtext:numbered-list'),
				run: run(toggleOrderedList()),
				active: () => state.orderedList,
			},
			{
				key: 'blockquote',
				tool: 'blockquote',
				icon: 'blockquote-right',
				label: __('richtext:blockquote'),
				run: run(toggleBlockquote()),
				active: () => state.blockquote,
			},
			{
				key: 'hr',
				tool: 'hr',
				icon: 'hr',
				label: __('richtext:horizontal-line'),
				run: run(insertHorizontalRule()),
			},
			{
				key: 'link',
				tool: 'link',
				icon: 'link-45deg',
				label: __('richtext:add-page-link'),
				run: link,
			},
			{
				key: 'unlink',
				tool: 'link',
				icon: 'slash-circle',
				label: __('richtext:remove-link'),
				run: run(unsetLink()),
				visible: () => state.link,
			},
			{ key: 'image', tool: 'image', icon: 'image', label: __('image:insert'), run: image },
			{
				key: 'br',
				tool: 'br',
				icon: 'arrow-return-left',
				label: __('richtext:hard-break'),
				run: run(insertHardBreak()),
			},
			{
				key: 'clear',
				tool: 'clear',
				icon: 'eraser',
				label: __('richtext:remove-formats'),
				run: run(clearMarks()),
			},
		].filter((spec) => tools.has(spec.tool));

		/** @type {(() => void)[]} */
		const updates = [];

		if (bubble) {
			const blockSpecs = specs.filter((spec) => BLOCK_STYLES.has(spec.tool));
			const blockIcon = () => blockSpecs.find((spec) => spec.active?.())?.icon ?? 'paragraph';

			if (blockSpecs.length > 0) {
				const { wrap, trigger, menu } = dropdown(
					`${menuId}-block-style`,
					[svg('paragraph'), svg('chevron-down')],
					{
						class: 'richtext-toolbar-btn cms-richtext-block-style',
						title: __('richtext:block-style'),
						'aria-label': __('richtext:block-style'),
					},
				);
				trigger.addEventListener('mousedown', keepFocus);
				entry(updates, menu, {
					icon: 'paragraph',
					label: __('richtext:paragraph'),
					run: run(setParagraph()),
					checked: () => blockIcon() === 'paragraph',
					bubbled: true,
				});

				for (const spec of blockSpecs) {
					entry(updates, menu, {
						icon: spec.icon,
						label: spec.label,
						run: spec.run,
						checked: () => spec.active?.() ?? false,
						bubbled: true,
					});
				}

				let shown = 'paragraph';
				updates.push(() => {
					const name = blockIcon();

					if (name !== shown) {
						trigger.firstElementChild?.replaceWith(svg(name));
						shown = name;
					}
				});
				bubble.append(wrap);
			}

			for (const spec of specs.filter(
				(spec) => !BUBBLELESS.has(spec.tool) && !BLOCK_STYLES.has(spec.tool),
			)) {
				tool(updates, bubble, spec, 'bubble');
			}
		}

		if (!readonly && mode !== 'inline') {
			root.prepend(
				this.#toolbar({
					menuId,
					specs,
					tools,
					updates,
					run,
					source,
					content,
					sourceBox,
					onSource: (next) => {
						showSource = next;
						showFallback();
					},
				}),
			);
		}

		this.#editor = createEditor({
			element: content,
			content: map[active],
			mode,
			bubbleElement: bubble,
			assetUrl: (uid) => this.#assetUrl(uid),
			editable: () => !readonly,
			placeholder: readonly ? '' : (field.placeholder ?? ''),
			onUpdate: (doc) => {
				map[active] = doc;
				showFallback();
				this.#notify();
			},
			onStateChange: (editorState) => {
				Object.assign(state, this.#state(editorState));

				for (const update of updates) update();
			},
		});

		return root;
	}

	/**
	 * The toolbar: configured paragraph classes and text styles as menus, the
	 * tools as buttons with an overflow menu for narrow widths, and the
	 * switch to the HTML source.
	 *
	 * @param {object} parts
	 * @param {string} parts.menuId
	 * @param {ToolSpec[]} parts.specs
	 * @param {Set<string>} parts.tools
	 * @param {(() => void)[]} parts.updates
	 * @param {(command: (state: EditorState, dispatch?: any, view?: any) => boolean) => () => void} parts.run
	 * @param {HTMLTextAreaElement} parts.source
	 * @param {HTMLElement} parts.content
	 * @param {HTMLElement} parts.sourceBox
	 * @param {(shown: boolean) => void} parts.onSource
	 * @returns {HTMLElement}
	 */
	#toolbar({ menuId, specs, tools, updates, run, source, content, sourceBox, onSource }) {
		const toolbar = create(
			'div',
			'richtext-toolbar cms-richtext-toolbar cms-richtext-toolbar-open',
		);
		const classes = Object.entries(this.field.richtextClasses ?? {});
		const styles = Object.entries(this.field.richtextStyles ?? {});
		/** @type {HTMLElement[]} */
		const editing = [];
		const back = create('div', 'richtext-extras cms-richtext-extras-source');
		const backButton = document.createElement('button');
		backButton.className = 'richtext-source-btn cms-richtext-source-btn-compact';
		const backLabel = create('span', 'cms-richtext-source-label');
		backLabel.textContent = __('richtext:show-content');
		backButton.append(svg('file-earmark-richtext'), backLabel);
		back.append(backButton);

		/** @param {boolean} shown */
		const showSource = (shown) => {
			if (shown) {
				source.value = this.#editor?.getHTML() ?? '';
			}

			toolbar.classList.toggle('cms-richtext-toolbar-open', !shown);
			content.classList.toggle('hide', shown);
			sourceBox.classList.toggle('hide', !shown);
			toolbar.replaceChildren(...(shown ? [back] : editing));
			onSource(shown);
		};

		backButton.addEventListener('click', () => showSource(false));
		// setContent dispatches a changed transaction, which routes the parsed
		// document back through onUpdate.
		source.addEventListener('keyup', () => this.#editor?.setContent(source.value));

		if (classes.length > 0) {
			const { wrap, menu } = dropdown(
				`${menuId}-paragraph`,
				[document.createTextNode(__('richtext:paragraph')), svg('chevron-down')],
				{
					class: 'richtext-dropdown-button',
				},
			);
			const current = () => this.#currentClass;
			entry(updates, menu, {
				icon: 'paragraph',
				label: __('richtext:paragraph'),
				run: run(setParagraph()),
				checked: () => current() === 'default',
			});

			for (const [name, label] of classes) {
				entry(updates, menu, {
					icon: 'type',
					label,
					run: run(setParagraphClass(name)),
					checked: () => current() === name,
				});
			}

			entry(updates, menu, {
				icon: 'eraser',
				label: __('richtext:remove-format'),
				run: run(clearNodes()),
			});
			editing.push(wrap);
		}

		if (styles.length > 0) {
			const { wrap, menu } = dropdown(`${menuId}-style`, [svg('fonts'), svg('chevron-down')], {
				class: 'richtext-dropdown-button',
				'aria-label': __('richtext:text-style'),
			});
			const current = () => this.#currentStyle;

			for (const [name, label] of styles) {
				entry(updates, menu, {
					icon: null,
					label,
					run: run(setStyle(name)),
					checked: () => current() === name,
				});
			}

			entry(updates, menu, {
				icon: 'eraser',
				label: __('richtext:remove-style'),
				run: run(unsetStyle()),
			});
			editing.push(wrap);
		}

		const overflow = dropdown(menuId, [svg('three-dots-vertical')], {
			id: `${menuId}-trigger`,
			class: 'richtext-dropdown-button cms-richtext-compact-tools-button',
			title: __('richtext:formatting-tools'),
			'aria-label': __('richtext:formatting-tools'),
		});
		overflow.wrap.classList.add('cms-richtext-toolbar-compact-actions');
		overflow.menu.style.setProperty('--width', '18rem');
		const buttons = create(
			'div',
			'richtext-toolbar-btns cms-richtext-toolbar-btns-grow cms-richtext-toolbar-main-actions',
		);

		for (const spec of specs) {
			tool(updates, overflow.menu, spec, 'menu');
			tool(updates, buttons, spec, 'button');
		}

		editing.push(overflow.wrap, buttons);

		if (tools.has('source')) {
			const extras = create('div', 'richtext-extras');
			const toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.className = 'richtext-source-btn cms-richtext-source-btn-offset';
			const label = create('span', 'cms-richtext-toolbar-source-label');
			label.textContent = __('richtext:show-source');
			toggle.append(svg('code-slash'), label);
			toggle.addEventListener('click', () => showSource(true));
			extras.append(toggle);
			editing.push(extras);
		}

		toolbar.append(...editing);

		return toolbar;
	}

	/** @type {string | null} */
	#currentClass = null;
	/** @type {string | null} */
	#currentStyle = null;

	/**
	 * The formatting at the selection, as the tools show it.
	 *
	 * @param {EditorState} state
	 */
	#state(state) {
		const paragraph = isNodeActive(state, schema.nodes.paragraph);
		const align = getActiveTextAlign(state);
		this.#currentClass = paragraph
			? (getBlockAttributes(state, schema.nodes.paragraph)?.class ?? 'default')
			: null;
		this.#currentStyle = getMarkAttributes(state, schema.marks.style)?.class ?? null;

		return {
			bold: isMarkActive(state, schema.marks.bold),
			heading1: isNodeActive(state, schema.nodes.heading, { level: 1 }),
			heading2: isNodeActive(state, schema.nodes.heading, { level: 2 }),
			heading3: isNodeActive(state, schema.nodes.heading, { level: 3 }),
			paragraphClass: this.#currentClass,
			center: align === 'center',
			right: align === 'right',
			justify: align === 'justify',
			italic: isMarkActive(state, schema.marks.italic),
			strike: isMarkActive(state, schema.marks.strike),
			bulletList: isNodeActive(state, schema.nodes.bulletList),
			orderedList: isNodeActive(state, schema.nodes.orderedList),
			subscript: isMarkActive(state, schema.marks.subscript),
			superscript: isMarkActive(state, schema.marks.superscript),
			blockquote: isNodeActive(state, schema.nodes.blockquote),
			link: isMarkActive(state, schema.marks.link),
			styleClass: this.#currentStyle,
		};
	}
}

if (!customElements.get('cosray-richtext')) {
	customElements.define('cosray-richtext', CosrayRichtext);
}
