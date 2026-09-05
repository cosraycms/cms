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
// A row's summary lines name the sub-field each was rendered from; while
// the editor types into that sub-field, only that line follows, so a
// line drawn from something the client cannot read (richtext) stays as
// the server rendered it. The header thus stays truthful with the form
// open, and stamped rows, whose lines name nothing yet, take the first
// two text-like fields the editor fills in declaration order. The
// row's kebab (a <details>) closes after an action and on any click
// outside it. Row lists reorder by drag on their grips through Sortable,
// loaded on demand so only screens with such a list pay for it.

import type { SortableEvent } from 'sortablejs';

import { uid } from '$lib/content';
import type { CosrayHost } from '$lib/host';

const enhanced = new WeakSet<HTMLElement>();

const escapeRegex = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

type Renaming = {
	namePattern: RegExp;
	idPattern: RegExp;
	name: string;
	id: string;
};

function rewrite(scope: ParentNode, renaming: Renaming): void {
	scope.querySelectorAll<HTMLElement>('[name]').forEach((el) => {
		const name = el.getAttribute('name') ?? '';
		el.setAttribute('name', name.replace(renaming.namePattern, renaming.name));
	});
	scope.querySelectorAll<HTMLElement>('[id]').forEach((el) => {
		el.id = el.id.replace(renaming.idPattern, renaming.id);
	});
	scope.querySelectorAll<HTMLElement>('label[for]').forEach((el) => {
		const target = el.getAttribute('for') ?? '';
		el.setAttribute('for', target.replace(renaming.idPattern, renaming.id));
	});
	// Nested containers renumber against their data-name/data-id; keep
	// those bases in sync with the renamed inputs.
	scope.querySelectorAll<HTMLElement>('[data-repeater]').forEach((nested) => {
		nested.dataset.name = (nested.dataset.name ?? '').replace(renaming.namePattern, renaming.name);
		nested.dataset.id = (nested.dataset.id ?? '').replace(renaming.idPattern, renaming.id);
	});
	// querySelectorAll cannot see into template content; recurse so the
	// rows a nested template will stamp carry the renamed outer base.
	scope.querySelectorAll<HTMLTemplateElement>('template').forEach((template) => {
		rewrite(template.content, renaming);
	});
}

function list(container: HTMLElement): HTMLElement {
	return container.querySelector<HTMLElement>(':scope > [data-repeater-list]') ?? container;
}

function renumber(container: HTMLElement): void {
	const nameBase = container.dataset.name ?? '';
	const idBase = container.dataset.id ?? '';
	const namePattern = new RegExp(`^${escapeRegex(nameBase)}\\[(?:\\d+|__i__)\\]`);
	const idPattern = new RegExp(`^${escapeRegex(idBase)}-(?:\\d+|__i__)`);
	const rows = list(container).querySelectorAll<HTMLElement>(':scope > [data-repeater-row]');

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

	const count = container.querySelector<HTMLElement>(':scope > [data-repeater-count]');

	if (count) {
		const template = rows.length === 1 ? count.dataset.one : count.dataset.many;
		count.textContent = (template ?? '').replace(':count', String(rows.length));
	}

	const max = Number(container.dataset.max ?? '');
	const full = Number.isFinite(max) && max > 0 && rows.length >= max;

	container
		.querySelectorAll<HTMLElement>(':scope > [data-repeater-footer] [data-repeater-add]')
		.forEach((add) => {
			add.hidden = full;
		});
}

function changed(container: HTMLElement): void {
	renumber(container);
	container.dispatchEvent(new Event('change', { bubbles: true }));
}

type Anchor = { row: HTMLElement; where: 'before' | 'after' };

function add(
	container: HTMLElement,
	type: string | null,
	at: Anchor | null,
	prepare?: (clone: DocumentFragment) => void,
): void {
	const templates = [
		...container.querySelectorAll<HTMLTemplateElement>(':scope > template[data-repeater-template]'),
	];
	const template =
		type === null
			? templates[0]
			: templates.find((el) => el.getAttribute('data-repeater-template') === type);
	const rows = list(container);
	const footer =
		rows === container ? container.querySelector(':scope > [data-repeater-footer]') : null;

	if (!template || (rows === container && !footer)) {
		return;
	}

	const clone = template.content.cloneNode(true) as DocumentFragment;
	const stamped = clone.querySelector<HTMLElement>('[data-repeater-row]');

	// Fresh rows need a stable identity before their first save; the
	// server backfills missing uids as a safety net. uid() rather than
	// crypto.randomUUID(): dev servers on http://*.local are not secure
	// contexts, and it matches the server's uid format.
	clone.querySelectorAll<HTMLInputElement>('[data-repeater-uid]').forEach((input) => {
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
	stamped?.dispatchEvent(new CustomEvent('repeater:stamp', { bubbles: true }));
	changed(container);
	stamped?.querySelector<HTMLElement>('input:not([type="hidden"]), textarea, select')?.focus();
}

const CONTROL = 'input, textarea, select';

/** A control's name below its row, the same in the source and its copy. */
function relative(name: string, container: HTMLElement): string {
	const base = escapeRegex(container.dataset.name ?? '');

	return name.replace(new RegExp(`^${base}\\[(?:\\d+|__i__)\\]`), '');
}

function copy(source: HTMLElement, clone: DocumentFragment, container: HTMLElement): void {
	const controls = new Map<string, HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>();
	const hosts = new Map<string, Element>();

	// Unnamed controls are not submitted; they mirror state the row
	// derives from what is, and follow it on their own.
	source
		.querySelectorAll<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>(CONTROL)
		.forEach((control) => {
			if (control.name !== '') {
				controls.set(relative(control.name, container), control);
			}
		});
	source.querySelectorAll('cosray-host').forEach((host) => {
		hosts.set(relative(host.getAttribute('name') ?? '', container), host);
	});

	// The uid keeps the fresh one the stamp gave it; a nested row list the
	// template stamps empty stays empty, since nothing in it has a match.
	clone
		.querySelectorAll<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>(CONTROL)
		.forEach((control) => {
			const from = control.name === '' ? null : controls.get(relative(control.name, container));

			if (!from || control.hasAttribute('data-repeater-uid')) {
				return;
			}

			if (
				control instanceof HTMLInputElement &&
				(control.type === 'checkbox' || control.type === 'radio')
			) {
				control.checked = (from as HTMLInputElement).checked;
			} else {
				control.value = from.value;
			}
		});

	// A host reads its payload from the embedded script when it connects,
	// so the copy is seeded there; the source's edits are on the element,
	// its script only says what the server rendered.
	clone.querySelectorAll('cosray-host').forEach((host) => {
		const from = hosts.get(relative(host.getAttribute('name') ?? '', container));
		const script = host.querySelector(':scope > script[type="application/json"]');
		const payload = (from as Partial<CosrayHost> | undefined)?.payload;

		if (!from || !script) {
			return;
		}

		script.textContent =
			payload === undefined
				? (from.querySelector(':scope > script[type="application/json"]')?.textContent ?? '')
				: JSON.stringify(payload);
	});
}

function duplicate(source: HTMLElement, container: HTMLElement): void {
	const type = source.querySelector<HTMLInputElement>(':scope > input[name$="[type]"]');

	add(container, type?.value || null, { row: source, where: 'after' }, (clone) => {
		copy(source, clone, container);
	});
}

function move(mover: Element): void {
	const direction = mover.getAttribute('data-repeater-move');
	const row = mover.closest<HTMLElement>('[data-repeater-row]');
	const container = mover.closest<HTMLElement>('[data-repeater]');

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

function fieldOf(input: Element): string | null {
	return /\[fields\]\[([^\]]+)\]/.exec(input.getAttribute('name') ?? '')?.[1] ?? null;
}

/** The row's own text-like inputs, keyed by sub-field, in form order. */
function fields(row: HTMLElement): Map<string, Array<HTMLInputElement | HTMLTextAreaElement>> {
	const body = row.querySelector<HTMLElement>(':scope > [data-repeater-body]');
	const result = new Map<string, Array<HTMLInputElement | HTMLTextAreaElement>>();

	// Own fields only: a nested repeater's rows and the meta dialogs are
	// not part of the summary, matching the server-side rule.
	body?.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>(TEXT_LIKE).forEach((input) => {
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

function value(inputs: Array<HTMLInputElement | HTMLTextAreaElement> | undefined): string {
	return inputs?.map((input) => input.value.trim()).find((text) => text !== '') ?? '';
}

function summarize(row: HTMLElement, changed: string): void {
	const title = row.querySelector<HTMLElement>('[data-repeater-title]');
	const subtitle = row.querySelector<HTMLElement>('[data-repeater-subtitle]');

	if (!title) {
		return;
	}

	const own = fields(row);
	const lines = [title, subtitle].filter((line): line is HTMLElement => line !== null);
	const attribute = (line: HTMLElement): string =>
		line === title ? 'data-repeater-title' : 'data-repeater-subtitle';
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

function onInput(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element) || !target.matches(TEXT_LIKE)) {
		return;
	}

	const row = target.closest<HTMLElement>('[data-repeater-row]');
	const field = fieldOf(target);

	if (row && field !== null) {
		summarize(row, field);
	}
}

function closeMenus(except: Element | null): void {
	document
		.querySelectorAll<HTMLDetailsElement>('details[data-repeater-menu][open]')
		.forEach((menu) => {
			if (menu !== except) {
				menu.open = false;
			}
		});
}

function onClick(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	closeMenus(target.closest('details[data-repeater-menu]'));

	const remove = target.closest('[data-repeater-remove]');

	if (remove) {
		const container = remove.closest<HTMLElement>('[data-repeater]');
		remove.closest('[data-repeater-row]')?.remove();

		if (container) {
			changed(container);
		}

		return;
	}

	const mover = target.closest('[data-repeater-move]');

	if (mover) {
		closeMenus(null);
		move(mover);

		return;
	}

	const duplicator = target.closest('[data-repeater-duplicate]');
	const source = duplicator?.closest<HTMLElement>('[data-repeater-row]');
	const owner = source?.closest<HTMLElement>('[data-repeater]');

	if (duplicator && source && owner && source.parentElement === list(owner)) {
		closeMenus(null);
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
	const container = adder?.closest<HTMLElement>('[data-repeater]');

	if (adder && container) {
		const type = adder.getAttribute('data-repeater-add');
		const where = adder.getAttribute('data-repeater-insert');
		const row = adder.closest<HTMLElement>('[data-repeater-row]');
		// Only a row of this container anchors; an adder in a nested
		// repeater's row still appends to its own list.
		const at: Anchor | null =
			(where === 'before' || where === 'after') && row && row.parentElement === list(container)
				? { row, where }
				: null;

		closeMenus(null);
		add(container, type === null || type === '' ? null : type, at);
	}
}

async function initDrag(): Promise<void> {
	const lists = [...document.querySelectorAll<HTMLElement>('[data-repeater-list]')].filter(
		(list) => !enhanced.has(list),
	);

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
			onEnd: (event: SortableEvent) => {
				const container = list.closest<HTMLElement>('[data-repeater]');

				if (container && event.oldIndex !== event.newIndex) {
					changed(container);
				}
			},
		});
	}
}

export function install(): () => void {
	const rescan = (): void => {
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
