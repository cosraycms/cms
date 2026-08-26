// Repeater rows: add clones a server-rendered template (typed repeaters
// like entries carry one template per row type and typed add buttons),
// remove drops the row, move swaps it with a sibling row. Renumbering
// keeps input names, ids and row labels dense so submissions stay
// ordered (the server normalizes gaps anyway). It also rewrites the
// data-name/data-id bases of nested repeater containers and recurses
// into inert template content, so structural controls nested inside a
// row keep renumbering against the right base after their row moved.

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

function renumber(container: HTMLElement): void {
	const nameBase = container.dataset.name ?? '';
	const idBase = container.dataset.id ?? '';
	const namePattern = new RegExp(`^${escapeRegex(nameBase)}\\[(?:\\d+|__i__)\\]`);
	const idPattern = new RegExp(`^${escapeRegex(idBase)}-(?:\\d+|__i__)`);
	const rows = container.querySelectorAll<HTMLElement>(':scope > [data-repeater-row]');

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

function add(container: HTMLElement, type: string | null): void {
	const templates = [
		...container.querySelectorAll<HTMLTemplateElement>(':scope > template[data-repeater-template]'),
	];
	const template =
		type === null
			? templates[0]
			: templates.find((el) => el.getAttribute('data-repeater-template') === type);
	const anchor = container.querySelector(':scope > [data-repeater-footer]');

	if (!template || !anchor) {
		return;
	}

	const clone = template.content.cloneNode(true) as DocumentFragment;

	// Fresh rows need a stable identity before their first save; the
	// server backfills missing uids as a safety net.
	clone.querySelectorAll<HTMLInputElement>('[data-repeater-uid]').forEach((input) => {
		if (input.value === '') {
			input.value = crypto.randomUUID();
		}
	});

	anchor.before(clone);
	changed(container);
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

function onClick(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

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
		move(mover);

		return;
	}

	const adder = target.closest('[data-repeater-add]');
	const container = adder?.closest<HTMLElement>('[data-repeater]');

	if (adder && container) {
		const type = adder.getAttribute('data-repeater-add');
		add(container, type === null || type === '' ? null : type);
	}
}

export function install(): () => void {
	document.addEventListener('click', onClick);

	return () => {
		document.removeEventListener('click', onClick);
	};
}
