// Repeater rows: add clones a server-rendered template (typed repeaters
// like entries carry one template per row type and typed add buttons)
// at the end of the list, or before or after the row an add button sits
// in when it says so (blocks: the inserters on a row), and focuses the
// stamped row's first input; remove drops the row, move swaps it with a
// sibling row. Duplicate stamps the row's own template after it and
// copies the live values control by control — typed values live on DOM
// properties, and an element host keeps its edits to itself, so a plain
// clone of the row would carry what the server last rendered instead.
// Renumbering keeps input names, ids and row labels dense
// so submissions stay ordered (the server normalizes gaps anyway). It
// also rewrites the data-name/data-id bases of nested repeater
// containers and recurses into inert template content, so structural
// controls nested inside a row keep renumbering against the right base
// after their row moved.
// Rows sit directly in the container, or in a [data-repeater-list]
// child when the container also carries chrome around them (entries:
// a count line, the footer); the count line follows the row count.
// A nested container without templates of its own (a blocks split)
// borrows the enclosing one's, renamed to its own base, and a row moves
// between two containers by rebasing its names from one to the other.
// A row's summary lines name the sub-field each was rendered from; while
// the editor types into that sub-field, only that line follows, so a
// line drawn from something the client cannot read (richtext) stays as
// the server rendered it. The header thus stays truthful with the form
// open, and stamped rows, whose lines name nothing yet, take the first
// two text-like fields the editor fills in declaration order. The
// row lists reorder by drag on their grips through Sortable,
// loaded on demand so only screens with such a list pay for it.

/** @import { SortableEvent } from 'sortablejs' */
/** @import { CosrayHost } from '../lib/host.js' */

import { uid } from '../lib/content.js';

const enhanced = /** @type {WeakSet<HTMLElement>} */ (new WeakSet());

/**
 * @param {string} value
 */
const escapeRegex = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * @typedef {object} Renaming
 * @property {RegExp} namePattern
 * @property {RegExp} idPattern
 * @property {string} name
 * @property {string} id
 */

/**
 * @param {ParentNode} scope
 * @param {Renaming} renaming
 */
function rewrite(scope, renaming) {
	/** @type {NodeListOf<HTMLElement>} */ (scope.querySelectorAll('[name]')).forEach((el) => {
		const name = el.getAttribute('name') ?? '';
		el.setAttribute('name', name.replace(renaming.namePattern, renaming.name));
	});
	/** @type {NodeListOf<HTMLElement>} */ (scope.querySelectorAll('[id]')).forEach((el) => {
		el.id = el.id.replace(renaming.idPattern, renaming.id);
	});
	/** @type {NodeListOf<HTMLElement>} */ (scope.querySelectorAll('label[for]')).forEach((el) => {
		const target = el.getAttribute('for') ?? '';
		el.setAttribute('for', target.replace(renaming.idPattern, renaming.id));
		const base = el.dataset.localeLabelFor;

		if (base !== undefined) {
			el.dataset.localeLabelFor = base.replace(renaming.idPattern, renaming.id);
		}
	});
	for (const attribute of ['popovertarget', 'aria-labelledby', 'aria-describedby']) {
		/** @type {NodeListOf<HTMLElement>} */ (scope.querySelectorAll(`[${attribute}]`)).forEach(
			(element) => {
				const targets = (element.getAttribute(attribute) ?? '').split(/\s+/);
				element.setAttribute(
					attribute,
					targets.map((target) => target.replace(renaming.idPattern, renaming.id)).join(' '),
				);
			},
		);
	}
	// Nested containers renumber against their data-name/data-id; keep
	// those bases in sync with the renamed inputs.
	/** @type {NodeListOf<HTMLElement>} */ (scope.querySelectorAll('[data-repeater]')).forEach(
		(nested) => {
			nested.dataset.name = (nested.dataset.name ?? '').replace(
				renaming.namePattern,
				renaming.name,
			);
			nested.dataset.id = (nested.dataset.id ?? '').replace(renaming.idPattern, renaming.id);
		},
	);
	// querySelectorAll cannot see into template content; recurse so the
	// rows a nested template will stamp carry the renamed outer base.
	/** @type {NodeListOf<HTMLTemplateElement>} */ (scope.querySelectorAll('template')).forEach(
		(template) => {
			rewrite(template.content, renaming);
		},
	);
}

/**
 * A row renamed for another container: its names and ids leave the
 * source's base for the target's, indexed by the target's renumbering.
 *
 * @param {ParentNode} scope
 * @param {HTMLElement} from
 * @param {HTMLElement} to
 */
export function rebase(scope, from, to) {
	rewrite(scope, {
		namePattern: new RegExp(`^${escapeRegex(from.dataset.name ?? '')}\\[(?:\\d+|__i__)\\]`),
		idPattern: new RegExp(`^${escapeRegex(from.dataset.id ?? '')}-(?:\\d+|__i__)`),
		name: `${to.dataset.name ?? ''}[__i__]`,
		id: `${to.dataset.id ?? ''}-__i__`,
	});
}

/**
 * @param {HTMLElement} container
 * @returns {HTMLElement}
 */
function list(container) {
	return (
		/** @type {HTMLElement | null} */ (container.querySelector(':scope > [data-repeater-list]')) ??
		container
	);
}

/**
 * @param {HTMLElement} container
 */
export function renumber(container) {
	const nameBase = container.dataset.name ?? '';
	const idBase = container.dataset.id ?? '';
	const namePattern = new RegExp(`^${escapeRegex(nameBase)}\\[(?:\\d+|__i__)\\]`);
	const idPattern = new RegExp(`^${escapeRegex(idBase)}-(?:\\d+|__i__)`);
	const rows = /** @type {NodeListOf<HTMLElement>} */ (
		list(container).querySelectorAll(':scope > [data-repeater-row]')
	);
	// Renaming temporarily merges adjacent radio groups and can uncheck their selections.
	const checked = Array.from(
		/** @type {NodeListOf<HTMLInputElement>} */ (
			container.querySelectorAll('input[type="radio"]:checked')
		),
	);

	rows.forEach((row, index) => {
		rewrite(row, {
			namePattern,
			idPattern,
			name: `${nameBase}[${index}]`,
			id: `${idBase}-${index}`,
		});

		const label = row.querySelector('[data-repeater-label]');

		if (label) {
			label.textContent = `${index + 1}.`;
		}
	});

	checked.forEach((input) => {
		input.checked = true;
	});

	const count = /** @type {HTMLElement | null} */ (
		container.querySelector(':scope > [data-repeater-count]')
	);

	if (count) {
		const template = rows.length === 1 ? count.dataset.one : count.dataset.many;
		count.textContent = (template ?? '').replace(':count', String(rows.length));
	}

	const max = Number(container.dataset.max ?? '');
	const full = Number.isFinite(max) && max > 0 && rows.length >= max;

	/** @type {NodeListOf<HTMLElement>} */ (
		container.querySelectorAll(':scope > [data-repeater-footer] [data-repeater-add]')
	).forEach((add) => {
		add.hidden = full;
	});
}

/**
 * @param {HTMLElement} container
 */
export function changed(container) {
	renumber(container);
	container.dispatchEvent(new Event('change', { bubbles: true }));
}

/**
 * @typedef {object} Anchor
 * @property {HTMLElement} row
 * @property {'before' | 'after'} where
 */

/**
 * @typedef {object} Insertion
 * @property {HTMLElement} owner
 * @property {Anchor | null} at
 * @property {string | null} locale
 * @property {(clone: DocumentFragment) => void} [prepare] Runs on the stamped clone before it lands, for a caller that knows the row's layout.
 */

/**
 * @param {HTMLElement} owner
 * @returns {boolean}
 */
function active(owner) {
	return (
		owner.isConnected &&
		// A read-only field is ignored whole on save: adding, moving or
		// removing a row here would only lose the work.
		!owner.closest('[hidden], [inert], [data-readonly="true"]') &&
		owner.checkVisibility({ visibilityProperty: true })
	);
}

/**
 * @param {HTMLElement} owner
 * @returns {string | null}
 */
function locale(owner) {
	return owner.closest('[data-content-locale-scope]')?.getAttribute('data-content-locale') ?? null;
}

/**
 * @param {Element} trigger
 * @returns {Insertion | null}
 */
export function insertion(trigger) {
	const owner = /** @type {HTMLElement | null} */ (trigger.closest('[data-repeater]'));
	if (!owner || !active(owner) || trigger.matches(':disabled')) return null;
	const where = trigger.getAttribute('data-repeater-insert');
	const row = /** @type {HTMLElement | null} */ (trigger.closest('[data-repeater-row]'));
	if (where === 'before' || where === 'after') {
		if (!row) return null;
		if (row.parentElement === list(owner))
			return { owner, at: { row, where }, locale: locale(owner) };
	}
	return { owner, at: null, locale: locale(owner) };
}

/**
 * @param {Insertion} context
 * @param {string | null} type
 */
export function insert(context, type) {
	if (context.locale !== locale(context.owner)) return;
	add(context.owner, type, context.at, context.prepare);
}

/**
 * @param {HTMLElement} container
 * @param {string | null} type
 * @param {Anchor | null} at
 * @param {(clone: DocumentFragment) => void} [prepare]
 */
function add(container, type, at, prepare) {
	if (!active(container) || (at && at.row.parentElement !== list(container))) return;
	const own = templatesOf(container);
	const lender =
		own.length === 0
			? /** @type {HTMLElement | null} */ (container.parentElement?.closest('[data-repeater]'))
			: null;
	const templates = lender ? templatesOf(lender) : own;
	const template =
		type === null
			? templates[0]
			: templates.find((el) => el.getAttribute('data-repeater-template') === type);
	const rows = list(container);
	const footer =
		rows === container ? container.querySelector(':scope > [data-repeater-footer]') : null;

	if (!template) {
		return;
	}

	const clone = /** @type {DocumentFragment} */ (template.content.cloneNode(true));

	if (lender) {
		rebase(clone, lender, container);
	}

	const stamped = /** @type {HTMLElement | null} */ (clone.querySelector('[data-repeater-row]'));

	// Fresh rows need a stable identity before their first save; the
	// server backfills missing uids as a safety net. uid() rather than
	// crypto.randomUUID(): dev servers on http://*.local are not secure
	// contexts, and it matches the server's uid format.
	/** @type {NodeListOf<HTMLInputElement>} */ (
		clone.querySelectorAll('[data-repeater-uid]')
	).forEach((input) => {
		if (input.value === '') {
			input.value = uid();
		}
	});
	prepare?.(clone);

	if (at?.where === 'before') {
		at.row.before(clone);
	} else if (at?.where === 'after') {
		at.row.after(clone);
	} else if (footer) {
		footer.before(clone);
	} else {
		rows.append(clone);
	}

	// Whoever renders state off the row's inputs (blocks: the layout)
	// gets to do so before the change is announced.
	stamped?.dispatchEvent(new CustomEvent('repeater:stamp', { bubbles: true, detail: { at } }));
	changed(container);
	if (stamped) {
		const focus = [
			.../** @type {NodeListOf<HTMLElement>} */ (
				stamped.querySelectorAll(
					'input:not([type="hidden"]), textarea, select, [contenteditable="true"]',
				)
			),
		].find(
			(input) => !input.matches(':disabled') && input.checkVisibility({ visibilityProperty: true }),
		);
		if (focus) focus.focus();
		else focusRow(stamped);
	}
}

/**
 * @param {HTMLElement} container
 * @returns {HTMLTemplateElement[]}
 */
function templatesOf(container) {
	return [
		.../** @type {NodeListOf<HTMLTemplateElement>} */ (
			container.querySelectorAll(':scope > template[data-repeater-template]')
		),
	];
}

/**
 * Focus a row that has no control to take it: focusable for the hand-off
 * only, so the row behaves like a server-rendered one once focus moves on.
 *
 * @param {HTMLElement} row
 */
export function focusRow(row) {
	row.tabIndex = -1;
	row.addEventListener('focusout', () => row.removeAttribute('tabindex'), { once: true });
	row.focus();
	if (document.activeElement !== row) row.removeAttribute('tabindex');
}

const CONTROL = 'input, textarea, select';

/**
 * A control's name below its row, the same in the source and its copy.
 *
 * @param {string} name
 * @param {HTMLElement} container
 * @returns {string}
 */
function relative(name, container) {
	const base = escapeRegex(container.dataset.name ?? '');

	return name.replace(new RegExp(`^${base}\\[(?:\\d+|__i__)\\]`), '');
}

/**
 * The source row's values seeded into a stamped clone, control by
 * control where their names below the row match, or only those names
 * `only` keeps.
 *
 * @param {HTMLElement} source
 * @param {DocumentFragment} clone
 * @param {HTMLElement} container
 * @param {(name: string) => boolean} [only]
 */
export function copy(source, clone, container, only = () => true) {
	const controls =
		/** @type {Map<string, HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>} */ (
			new Map()
		);
	const hosts = /** @type {Map<string, Element>} */ (new Map());

	// Unnamed controls are not submitted; they mirror state the row
	// derives from what is, and follow it on their own.
	/** @type {NodeListOf<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>} */ (
		source.querySelectorAll(CONTROL)
	).forEach((control) => {
		if (control.name === '') return;
		const key = relative(control.name, container);
		const previous = controls.get(key);

		// A radio group shares a name; keep its selected input rather than its last option.
		if (
			control instanceof HTMLInputElement &&
			control.type === 'radio' &&
			previous instanceof HTMLInputElement &&
			previous.checked
		)
			return;

		controls.set(key, control);
	});
	source.querySelectorAll('cosray-host').forEach((host) => {
		hosts.set(relative(host.getAttribute('name') ?? '', container), host);
	});

	// The uid keeps the fresh one the stamp gave it; a nested row list the
	// template stamps empty stays empty, since nothing in it has a match.
	/** @type {NodeListOf<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>} */ (
		clone.querySelectorAll(CONTROL)
	).forEach((control) => {
		const key = relative(control.name, container);
		const from = control.name === '' || !only(key) ? null : controls.get(key);

		if (!from || control.hasAttribute('data-repeater-uid')) {
			return;
		}

		if (
			control instanceof HTMLInputElement &&
			(control.type === 'checkbox' || control.type === 'radio')
		) {
			control.checked =
				/** @type {HTMLInputElement} */ (from).checked &&
				(control.type !== 'radio' || control.value === from.value);
		} else {
			control.value = from.value;
		}
	});

	// A host reads its payload from the embedded script when it connects,
	// so the copy is seeded there; the source's edits are on the element,
	// its script only says what the server rendered.
	clone.querySelectorAll('cosray-host').forEach((host) => {
		const key = relative(host.getAttribute('name') ?? '', container);
		const from = only(key) ? hosts.get(key) : undefined;
		const script = host.querySelector(':scope > script[type="application/json"]');
		const payload = /** @type {Partial<CosrayHost> | undefined} */ (from)?.payload;

		if (!from || !script) {
			return;
		}

		script.textContent =
			payload === undefined
				? (from.querySelector(':scope > script[type="application/json"]')?.textContent ?? '')
				: JSON.stringify(payload);
	});
}

/**
 * @param {HTMLElement} source
 * @param {HTMLElement} container
 */
function duplicate(source, container) {
	const type = /** @type {HTMLInputElement | null} */ (
		source.querySelector(':scope > input[name$="[type]"]')
	);

	add(container, type?.value || null, { row: source, where: 'after' }, (clone) => {
		copy(source, clone, container);
	});
}

/**
 * @param {Element} mover
 */
function move(mover) {
	const direction = mover.getAttribute('data-repeater-move');
	const row = /** @type {HTMLElement | null} */ (mover.closest('[data-repeater-row]'));
	const container = /** @type {HTMLElement | null} */ (mover.closest('[data-repeater]'));

	if ((direction !== 'up' && direction !== 'down') || !row || !container) {
		return;
	}

	const sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;

	if (!(sibling instanceof HTMLElement) || !sibling.matches('[data-repeater-row]')) {
		return;
	}

	if (direction === 'up') {
		sibling.before(row);
	} else {
		sibling.after(row);
	}

	changed(container);
}

// Named inputs only: element controls (the image card's alt text, say)
// keep unnamed inputs of their own in the row, which are not fields.
const TEXT_LIKE = 'input[type="text"][name], input[type="number"][name], textarea[name]';

/**
 * @param {Element} input
 * @returns {string | null}
 */
function fieldOf(input) {
	return /\[fields\]\[([^\]]+)\]/.exec(input.getAttribute('name') ?? '')?.[1] ?? null;
}

/**
 * The row's own text-like inputs, keyed by sub-field, in form order.
 *
 * @param {HTMLElement} row
 * @returns {Map<string, Array<HTMLInputElement | HTMLTextAreaElement>>}
 */
function fields(row) {
	const body = /** @type {HTMLElement | null} */ (
		row.querySelector(':scope > [data-repeater-body]')
	);
	const result = /** @type {Map<string, Array<HTMLInputElement | HTMLTextAreaElement>>} */ (
		new Map()
	);

	// Own fields only: a nested repeater's rows and the meta dialogs are
	// not part of the summary, matching the server-side rule.
	/** @type {NodeListOf<HTMLInputElement | HTMLTextAreaElement>} */ (
		body?.querySelectorAll(TEXT_LIKE)
	).forEach((input) => {
		const field = fieldOf(input);

		if (
			field !== null &&
			input.closest('[data-repeater-row]') === row &&
			!input.closest('dialog')
		) {
			result.set(field, [...(result.get(field) ?? []), input]);
		}
	});

	return result;
}

/**
 * @param {Array<HTMLInputElement | HTMLTextAreaElement> | undefined} inputs
 * @returns {string}
 */
function value(inputs) {
	return inputs?.map((input) => input.value.trim()).find((text) => text !== '') ?? '';
}

/**
 * @param {HTMLElement} row
 * @param {string} changed
 */
function summarize(row, changed) {
	const title = /** @type {HTMLElement | null} */ (row.querySelector('[data-repeater-title]'));
	const subtitle = /** @type {HTMLElement | null} */ (
		row.querySelector('[data-repeater-subtitle]')
	);

	if (!title) {
		return;
	}

	const own = fields(row);
	const lines = [title, subtitle].filter(
		/** @returns {line is HTMLElement} */ (line) => line !== null,
	);
	/**
	 * @param {HTMLElement} line
	 * @returns {string}
	 */
	const attribute = (line) => (line === title ? 'data-repeater-title' : 'data-repeater-subtitle');
	const sources = lines.map((line) => line.getAttribute(attribute(line)) ?? '');

	// A line without a source claims the first text-like field with
	// content that no other line shows yet.
	lines.forEach((line, index) => {
		if (sources[index] !== '') {
			return;
		}

		for (const [field, inputs] of own) {
			if (!sources.includes(field) && value(inputs) !== '') {
				sources[index] = field;
				line.setAttribute(attribute(line), field);

				break;
			}
		}
	});

	lines.forEach((line, index) => {
		if (sources[index] !== changed) {
			return;
		}

		const text = value(own.get(changed));

		if (line === title) {
			line.textContent = text !== '' ? text : (line.dataset.fallback ?? '');
		} else {
			line.textContent = text;
		}
	});
}

/**
 * @param {Event} event
 */
function onInput(event) {
	const target = event.target;

	if (!(target instanceof Element) || !target.matches(TEXT_LIKE)) {
		return;
	}

	const row = /** @type {HTMLElement | null} */ (target.closest('[data-repeater-row]'));
	const field = fieldOf(target);

	if (row && field !== null) {
		summarize(row, field);
	}
}

/**
 * @param {Event} event
 */
function onClick(event) {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const remove = target.closest('[data-repeater-remove]');

	if (remove) {
		const container = /** @type {HTMLElement | null} */ (remove.closest('[data-repeater]'));
		remove.closest('[data-repeater-row]')?.remove();

		if (container) {
			changed(container);
		}

		return;
	}

	const mover = target.closest('[data-repeater-move]');

	// A placed block moves by position; the placement behavior took it.
	if (mover && event.defaultPrevented) {
		return;
	}

	if (mover) {
		move(mover);

		return;
	}

	const duplicator = target.closest('[data-repeater-duplicate]');
	const source = /** @type {HTMLElement | null} */ (duplicator?.closest('[data-repeater-row]'));
	const owner = /** @type {HTMLElement | null} */ (source?.closest('[data-repeater]'));

	if (duplicator && source && owner && source.parentElement === list(owner)) {
		duplicate(source, owner);

		return;
	}

	const collapse = target.closest('[data-repeater-collapse]');

	if (collapse) {
		const body = collapse
			.closest('[data-repeater-row]')
			?.querySelector(':scope > [data-repeater-body]');

		if (body instanceof HTMLElement) {
			const hidden = body.toggleAttribute('hidden');
			collapse.setAttribute('aria-expanded', hidden ? 'false' : 'true');
		}

		return;
	}

	const adder = target.closest('[data-repeater-add]');
	const context = adder ? insertion(adder) : null;

	if (adder && context) {
		insert(context, adder.getAttribute('data-repeater-add') || null);
	}
}

/** Every row list not yet draggable becomes so: on load, after a swap, and for a new split. */
export async function initDrag() {
	// A multi-column canvas drags by position, through the placement behavior.
	const lists = [
		.../** @type {NodeListOf<HTMLElement>} */ (document.querySelectorAll('[data-repeater-list]')),
	].filter((list) => !enhanced.has(list) && !list.matches('.cms-blocks-editor.is-grid > .grid'));

	if (lists.length === 0) {
		return;
	}

	const { default: Sortable } = await import('sortablejs');

	for (const list of lists) {
		if (enhanced.has(list)) {
			continue;
		}

		enhanced.add(list);
		new Sortable(list, {
			handle: '[data-repeater-grip]',
			draggable: '[data-repeater-row]',
			animation: 150,
			fallbackOnBody: true,
			onEnd: (/** @type {SortableEvent} */ event) => {
				const container = /** @type {HTMLElement | null} */ (list.closest('[data-repeater]'));

				if (container && event.oldIndex !== event.newIndex) {
					changed(container);
				}
			},
		});
	}
}

/** @returns {() => void} */
export function install() {
	const rescan = () => {
		void initDrag();
	};

	document.addEventListener('click', onClick);
	document.addEventListener('input', onInput);
	document.addEventListener('htmx:after:swap', rescan);
	rescan();

	return () => {
		document.removeEventListener('click', onClick);
		document.removeEventListener('input', onInput);
		document.removeEventListener('htmx:after:swap', rescan);
	};
}
